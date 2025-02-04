<?php

declare(strict_types=1);

namespace gldstdlib;

use gldstdlib\exception\EmptyFileException;
use gldstdlib\exception\FilesystemException;

use function gldstdlib\safe\file_get_contents;
use function gldstdlib\safe\file_put_contents;
use function gldstdlib\safe\filesize;

/**
 * Abstractielaag voor Amazon S3 Blue Billywig CDN.
 *
 * @phpstan-type ConfigType object{
 *     version: string,
 *     region: string,
 *     access_key_id: string,
 *     secret_access_key: string,
 *     bucket_naam: string,
 *     media_root: string,
 *     url_root: string
 * }
 */
class S3
{
    public const ACL_PRIVATE = 'private';
    public const ACL_PUBLIC = 'public-read';

    private \Aws\Sdk $sdk;
    private \Aws\S3\S3Client $sdk_s3;
    private string $version;
    private string $region;
    private string $access_key_id;
    private string $secret_access_key;
    private string $bucket_naam;
    private string $media_root;
    private string $url_root;
    private bool $is_stream_wrapper_registered;

    /**
     * Maakt een API koppelingobject
     *
     * @param ConfigType $config_data Configuratie
     */
    public function __construct(
        private Log $log,
        object $config_data,
    ) {
        $this->version = $config_data->version;
        $this->region = $config_data->region;
        $this->access_key_id = $config_data->access_key_id;
        $this->secret_access_key = $config_data->secret_access_key;
        $this->bucket_naam = $config_data->bucket_naam;
        $this->media_root = $config_data->media_root;
        $this->url_root = $config_data->url_root;
        $this->is_stream_wrapper_registered = false;
    }

    /**
     * Singleton classes kunnen niet worden gekloond.
     */
    private function __clone()
    {
    }

    /**
     * Geeft het SDK object.
     */
    private function get_sdk(): \Aws\Sdk
    {
        return $this->sdk ??= new \Aws\Sdk([
            'version' => $this->version,
            'region' => $this->region,
            'credentials' => $this->get_credentials(),
        ]);
    }

    /**
     * Geeft het S3 clientobject
     *
     * @return \Aws\S3\S3Client
     */
    private function get_client(): \Aws\S3\S3Client
    {
        return $this->sdk_s3 ??= $this->get_sdk()->createS3();
    }

    /**
     * @return array{
     *     key: string,
     *     secret: string
     * }
     */
    private function get_credentials(): array
    {
        return [
            'key' => $this->access_key_id,
            'secret' => $this->secret_access_key,
        ];
    }

    /**
     * Geeft het basispad op het CDN waar de media staat.
     */
    public function get_media_root(): string
    {
        return $this->media_root;
    }

    /**
     * Geeft de publieke basis-URL voor media op het CDN.
     */
    public function get_url_root(): string
    {
        return $this->url_root;
    }

    /**
     * Upload een bestand.
     *
     * @param $lokaal_pad Lokaal bronbestand. Dit moet een regulier bestand
     * zijn.
     * @param $remote_pad Pad op het CDN
     * @param $is_publiek
     * @param $content_type
     * @param $pogingen Aantal keer dat de upload herhaald wordt bij fouten.
     * @param ?callable(int, int): void $progress_callback Callbackfunctie (optioneel)
     * Callback parameters:
     * 1. int totaal aantal bytes
     * 2. int tot nu toe geüploade bytes.
     * @param $progress_callback
     * @param $state Status van de eerder mislukte upload (alleen voor recursie)
     *
     * @return \Aws\Result<string, mixed> Resultaatobject
     *
     * @throws FilesystemException Als het lokale bestand geen regulier bestand is.
     * @throws EmptyFileException Als het lokale bestand leeg is.
     * @throws \Exception Bij uploadfouten
     */
    public function upload(
        string $lokaal_pad,
        string $remote_pad,
        bool $is_publiek = true,
        ?string $content_type = null,
        int $pogingen = 5,
        ?callable $progress_callback = null,
        \Aws\Multipart\UploadState|null $state = null
    ): \Aws\Result {
        if (!\is_file($lokaal_pad)) {
            throw new FilesystemException(
                "\"{$lokaal_pad}\" kan niet worden herkend als regulier bestand"
            );
        }
        if (filesize($lokaal_pad) === 0) {
            \clearstatcache(true);
            if (filesize($lokaal_pad) === 0) {
                throw new EmptyFileException(
                    "\"{$lokaal_pad}\" is een leeg bestand"
                );
            }
        }
        $acl = $is_publiek
            ? self::ACL_PUBLIC
            : self::ACL_PRIVATE;
        $sub_params = [];
        if (isset($content_type)) {
            $sub_params['ContentType'] = $content_type;
        }
        $parameters = [
            'bucket' => $this->bucket_naam,
            'key' => $remote_pad,
            'acl' => $acl,
            'before_upload' => fn(\Aws\Command $command) => \gc_collect_cycles(),
            'params' => $sub_params,
        ];
        if (isset($state)) {
            $parameters['state'] = $state;
        }
        if (isset($progress_callback)) {
            $progress = new S3MultipartProgress(filesize($lokaal_pad), $progress_callback);
            $parameters['before_upload'] = [$progress, 'before_upload'];
        }
        $uploader = new \Aws\S3\MultipartUploader(
            $this->get_client(),
            $lokaal_pad,
            $parameters
        );
        try {
            return $uploader->upload();
        } catch (\Aws\Exception\MultipartUploadException $e) {
            if ($pogingen === 0) {
                $this->get_client()->abortMultipartUpload($e->getState()->getId());
                throw $e;
            } else {
                $this->log->notice(
                    'S3::upload("%s", "%s") mislukt. Doe nog een poging.',
                    $lokaal_pad,
                    $remote_pad
                );
                return $this->upload(
                    $lokaal_pad,
                    $remote_pad,
                    $is_publiek,
                    $content_type,
                    $pogingen - 1,
                    $progress_callback,
                    $e->getState()
                );
            }
        }
    }

    /**
     * Download een bestand.
     *
     * @param $remote_pad Pad op het CDN
     * @param $lokaal_pad Lokaal doelbestand.
     * @param $overschrijven Of het lokale bestand mag worden overschreven
     * (standaard true)
     *
     * @return \Aws\Result<string, mixed> Resultaatobject
     *
     * @throws FilesystemException Als het lokale bestand niet kan of mag worden
     * geschreven
     * @throws \Exception Bij downloadfouten
     * @throws \Aws\S3\Exception\S3Exception Als het pad op het CDN niet bestaat
     */
    public function download(
        string $remote_pad,
        string $lokaal_pad,
        bool $overschrijven = true
    ): \Aws\Result {
        if (!$overschrijven && \file_exists($lokaal_pad)) {
            throw new FilesystemException(\sprintf('"%s" bestaat al', $lokaal_pad));
        }
        return $this->get_client()->getObject([
            'Bucket' => $this->bucket_naam,
            'Key' => $remote_pad,
            'SaveAs' => $lokaal_pad,
        ]);
    }

    /**
     * Geeft de stream wrapper en zet streaming aan
     *
     * @param $remote_pad Pad op het CDN
     */
    private function get_stream_wrapper(string $remote_pad): string
    {
        if (!$this->is_stream_wrapper_registered) {
            $this->get_client()->registerStreamWrapper();
        }
        return \sprintf(
            's3://%s/%s',
            $this->bucket_naam,
            $remote_pad
        );
    }

    /**
     * Geef de inhoud van een bestand als string
     *
     * @param $remote_pad Pad op het CDN
     *
     * @return string Data
     */
    public function get_contents(string $remote_pad): string
    {
        return file_get_contents($this->get_stream_wrapper($remote_pad));
    }

    /**
     * Zet de inhoud van een string in een bestand
     *
     * @param $remote_pad Pad op het cdn
     * @param $data Data
     *
     * @return int Het aantal geschreven bytes
     */
    public function put_contents(string $remote_pad, mixed $data): int
    {
        return file_put_contents($this->get_stream_wrapper($remote_pad), $data);
    }

    /**
     * Download een bestand van het CDN naar een lokaal tijdelijk bestand.
     *
     * @param $remote_pad Pad op het CDN
     * @param $prefix Eerste deel van de gegenereerde bestandsnaam.
     * Standaard 's3'.
     *
     * @return string Pad naar het tijdelijke bestand.
     */
    public function download_tempbestand(string $remote_pad, string $prefix = 's3'): string
    {
        $temp_bestand = \tempnam(\sys_get_temp_dir(), $prefix);
        $this->download($remote_pad, $temp_bestand);
        return $temp_bestand;
    }

    /**
     * Geeft aan of een bestand bestaat op het CDN.
     *
     * @param $remote_pad Pad op het CDN.
     */
    public function exists(string $remote_pad): bool
    {
        return $this->get_client()->doesObjectExist(
            $this->bucket_naam,
            $remote_pad
        );
    }

    /**
     * Verwijdert een object op het CDN.
     * Doet niets als het pad niet bestaat.
     *
     * @param $remote_pad
     *
     * @return \Aws\Result<string, mixed>
     */
    public function delete(string $remote_pad): \Aws\Result
    {
        return $this->get_client()->deleteObject([
            'Bucket' => $this->bucket_naam,
            'Key' => $remote_pad,
        ]);
    }

    /**
     * Verwijdert een map recursief met alle inhoud op het CDN.
     * Doet niets als het pad niet bestaat.
     *
     * @param $remote_pad
     *
     * @throws \Aws\S3\Exception\DeleteMultipleObjectsException
     */
    public function batch_delete(string $remote_pad): void
    {
        $delete = \Aws\S3\BatchDelete::fromListObjects($this->get_client(), [
            'Bucket' => $this->bucket_naam,
            'Prefix' => $remote_pad,
        ]);
        $delete->delete();
    }

    /**
     * Kopieer een bestand van een cdn-locatie naar een cdn-locatie.
     * Alleen voor bestanden, niet voor mappen.
     *
     * @param $remote_src Bronpad op het CDN.
     * @param $remote_dst Doelpad op het CDN.
     *
     * @return \Aws\Result<string, mixed>
     *
     * @throws \Aws\S3\Exception\S3Exception
     * @throws \Aws\Exception\MultipartUploadException
     */
    public function remote_copy(string $remote_src, string $remote_dst): \Aws\Result
    {
        $copier = new \Aws\S3\ObjectCopier(
            $this->get_client(),
            [
                'Bucket' => $this->bucket_naam,
                'Key' => $remote_src,
            ],
            [
                'Bucket' => $this->bucket_naam,
                'Key' => $remote_dst,
            ],
            self::ACL_PUBLIC
        );
        return $copier->copy();
    }

    /**
     * Synchroniseert een map met een andere map op het CDN.
     * Alle bestanden uit de bron die nog niet bestaan in het doel worden ge-
     * kopieerd. Bestanden met dezelfde naam maar met een andere inhoud worden
     * overschreven.
     * Bestanden in het doel die niet bestaan in de bron worden verwijderd.
     * Mappen worden genegeerd. Er worden mappen aangemaakt als daar bestanden
     * in staan die worden gekopieerd. Lege mappen worden niet verwijderd.
     *
     * @param $remote_src Bronmap
     * @param $remote_dst Doelmap
     */
    public function remote_sync(string $remote_src, string $remote_dst): void
    {
        $remote_src = \rtrim($remote_src, '/') . '/';
        $remote_dst = \rtrim($remote_dst, '/') . '/';
        // Lijst met bestanden src
        $src_lijst = $this->remote_sync_get_lijst($remote_src);
        // Lijst met bestanden dst
        $dst_lijst = $this->remote_sync_get_lijst($remote_dst);
        // Filteren
        // Bestanden die bestaan in src maar niet in dst, of in beide bestaan met een andere checksum.
        // Deze worden gekopieerd.
        $src_lijst_nieuw = \array_diff_key($src_lijst, $dst_lijst);
        // Bestanden die bestaan in dst maar niet in src, of in beide bestaan met een andere checksum.
        $dst_lijst_oud = \array_diff_key($dst_lijst, $src_lijst);
        // Bestanden die bestaan in dst maar niet in src.
        // Deze worden verwijderd.
        $dst_lijst_oud = \array_diff($dst_lijst_oud, $src_lijst_nieuw);
        // Kopieren bestanden src
        foreach ($src_lijst_nieuw as $item) {
            $this->remote_copy($remote_src . $item, $remote_dst . $item);
        }
        // Verwijderen bestanden dst
        foreach ($dst_lijst_oud as $item) {
            $this->delete($remote_dst . $item);
        }
    }

    /**
     * @return array<string,string>
     */
    private function remote_sync_get_lijst(string $root_pad, bool $inclusief_mappen = false): array
    {
        $root_pad = \rtrim($root_pad, '/') . '/';
        $lijst = [];
        $objects = $this->get_client()->getPaginator('ListObjects', [
            'Bucket' => $this->bucket_naam,
            'Prefix' => $root_pad,
        ]);
        foreach ($objects as ['Contents' => $object_contents]) {
            foreach ($object_contents as $contents) {
                $key = $contents['Key'];
                $key = \substr($contents['Key'], \strlen($root_pad));
                $etag = \str_replace('"', '', $contents['ETag']);
                if (!$inclusief_mappen && \substr($key, -1) === '/') {
                    continue;
                }
                $hash = \sprintf(
                    '%s%s',
                    $etag,
                    $key
                );
                $lijst[$hash] = $key;
            }
        }
        return $lijst;
    }
}
