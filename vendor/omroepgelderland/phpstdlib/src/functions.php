<?php

declare(strict_types=1);

namespace gldstdlib;

use gldstdlib\exception\CompileErrorException;
use gldstdlib\exception\CompileWarningErrorException;
use gldstdlib\exception\CoreErrorException;
use gldstdlib\exception\CoreWarningErrorException;
use gldstdlib\exception\DeprecatedErrorException;
use gldstdlib\exception\ErrorErrorException;
use gldstdlib\exception\GLDException;
use gldstdlib\exception\IndexException;
use gldstdlib\exception\NoticeErrorException;
use gldstdlib\exception\NullException;
use gldstdlib\exception\ParseErrorException;
use gldstdlib\exception\RecoverableErrorException;
use gldstdlib\exception\StrictErrorException;
use gldstdlib\exception\TypeException;
use gldstdlib\exception\UndefinedPropertyException;
use gldstdlib\exception\UserDeprecatedErrorException;
use gldstdlib\exception\UserErrorException;
use gldstdlib\exception\UserNoticeErrorException;
use gldstdlib\exception\UserWarningErrorException;
use gldstdlib\exception\WarningErrorException;
use gldstdlib\safe\FilesystemException;
use gldstdlib\safe\SafeException;

use function gldstdlib\safe\curl_exec;
use function gldstdlib\safe\curl_init;
use function gldstdlib\safe\curl_setopt_array;
use function gldstdlib\safe\fclose;
use function gldstdlib\safe\filesize;
use function gldstdlib\safe\fopen;
use function gldstdlib\safe\fwrite;
use function gldstdlib\safe\iconv;
use function gldstdlib\safe\mime_content_type;
use function gldstdlib\safe\preg_match;
use function gldstdlib\safe\preg_replace;
use function gldstdlib\safe\rewind;
use function gldstdlib\safe\scandir;

/**
 * Recursively removes a directory.
 *
 * @param $dir Directory path
 */
function rrmdir(string $dir): void
{
    if (\is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != '.' && $object != '..') {
                if (\is_dir($dir . '/' . $object)) {
                    rrmdir($dir . '/' . $object);
                } else {
                    \unlink($dir . '/' . $object);
                }
            }
        }
        \rmdir($dir);
    }
}

/**
 * Plakt paden aan elkaar met slashes.
 *
 * @param ...$paths Meerdere paden die aan elkaar geplakt worden.
 *
 * @return string Het resulterende pad.
 */
function path_join(string ...$paths): string
{
    $path = '';
    foreach ($paths as $i => $arg) {
        if ($i == 0) {
            // eerste parameter
            $path = \rtrim($arg, '/') . '/';
        } elseif ($i == \count($paths) - 1) {
            // laatste parameter
            $path .= \ltrim($arg, '/');
        } else {
            $path .= \trim($arg, '/') . '/';
        }
    }
    return $path;
}

/**
 * Geeft de inhoud van een map en submappen
 *
 * @param $path Map om te scannen
 *
 * @return list<string>|string Array met de inhoud. Wanneer $path een bestand is wordt
 * een string met de bestandsnaam gegeven.
 */
function scandir_recursive(string $path)
{
    if (\is_dir($path)) {
        $lijst = [];
        foreach (scandir($path) as $subdir) {
            if ($subdir != '.' && $subdir != '..') {
                $sublijst = scandir_recursive(path_join($path, $subdir));
                if (\is_array($sublijst)) {
                    $lijst = \array_merge($lijst, $sublijst);
                } else {
                    $lijst[] = $sublijst;
                }
            }
        }
        return $lijst;
    } else {
        return $path;
    }
}

/**
 * Geeft aan of het script handmatig (niet via cron o.i.d.) via de commandline
 * is aangeroepen.
 */
function is_cli(): bool
{
    return \php_sapi_name() === 'cli' && (isset($_SERVER['TERM']) || isset($_SERVER['VSCODE_CWD']));
}

/**
 * Geeft aan of het script via een browser wordt aangeroepen.
 */
function is_browser(): bool
{
    return \php_sapi_name() !== 'cli';
}

/**
 * Geeft aan of het script niet-interactief via de commandline is aangeroepen.
 */
function is_cron(): bool
{
    return \php_sapi_name() === 'cli' && !isset($_SERVER['TERM']);
}

/**
 * Zet een tweecijferig jaartal om naar een van vier cijfers.
 * Dertig of hoger wordt gezien als twintigste eeuw, anders eenentwintigste
 * eeuw.
 *
 * @param $jaar Invoerjaar
 *
 * @return int Viercijferig jaartal
 *
 * @throws GLDException Als $jaar niet tussen 0 en 99 is.
 */
function y2k(int $jaar): int
{
    if ($jaar < 0 || $jaar > 99) {
        throw new GLDException(\sprintf(
            '%s is geen jaartal van twee cijfers',
            $jaar
        ));
    }
    if ($jaar >= 30) {
        return 1900 + $jaar;
    } else {
        return 2000 + $jaar;
    }
}

/**
 * Geeft aan of een bestand door een ander proces geschreven wordt.
 * Dit is bedoeld voor bestanden die gekopiëerd of geüpload worden.
 *
 * @param $pad Pad naar het bestand
 *
 * @return bool True als het bestand niet groter of kleiner aan het worden is.
 *
 * @throws \Exception Als het bestand niet bestaat of als de grootte ervan niet
 * kan worden bepaald.
 */
function is_bestand_klaar(string $pad): bool
{
    \clearstatcache(true, $pad);
    $grootte_voor = filesize($pad);
    \sleep(1);
    \clearstatcache(true, $pad);
    $grootte_na = filesize($pad);
    return ( $grootte_voor === $grootte_na );
}

/**
 * Wacht tot een extern proces klaar is met kopiëren/uploaden van een bestand.
 *
 * @param $pad Pad naar het bestand
 * @param $timeout Maximumtijd dat er gewacht wordt.
 *
 * @throws \Exception Als het bestand niet bestaat of als de grootte ervan niet
 * kan worden bepaald.
 * @throws GLDException Als het wachten langer duurt dan $timeout
 */
function wacht_tot_bestand_klaar_is(string $pad, int|\DateInterval $timeout): void
{
    if (\is_int($timeout)) {
        $timeout = new \DateInterval("PT{$timeout}S");
    }
    $timeout_tijd = new \DateTime();
    $timeout_tijd->add($timeout);
    while (!is_bestand_klaar($pad)) {
        $nu = new \DateTime();
        if ($nu > $timeout_tijd) {
            throw new GLDException(\sprintf(
                'Het wachten op de beschikbaarheid van "%s" duurde langer dan %s',
                $pad,
                $timeout->format('%im %ss')
            ));
        }
    }
}

/**
 * Overkoepelende error handler voor aanroep met exception_error_handler.
 * Maakt een echte PHP exception bij fouten.
 */
function exception_error_handler(
    int $errno,
    string $errstr,
    string $errfile,
    int $errline
): bool {
    if (\str_contains($errfile, '/vendor/')) {
        // Negeer errors in modules
        // Log::notice("Error in module: {$errno} {$errstr} in {$errfile} regel {$errline}");
        return true;
    }
    switch ($errno) {
        case \E_ERROR:
            throw new ErrorErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_WARNING:
            throw new WarningErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_PARSE:
            throw new ParseErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_NOTICE:
            throw new NoticeErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_CORE_ERROR:
            throw new CoreErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_CORE_WARNING:
            throw new CoreWarningErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_COMPILE_ERROR:
            throw new CompileErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_COMPILE_WARNING:
            throw new CompileWarningErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_USER_ERROR:
            throw new UserErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_USER_WARNING:
            throw new UserWarningErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_USER_NOTICE:
            throw new UserNoticeErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_STRICT:
            throw new StrictErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_RECOVERABLE_ERROR:
            throw new RecoverableErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_DEPRECATED:
            throw new DeprecatedErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_USER_DEPRECATED:
            throw new UserDeprecatedErrorException($errstr, 0, $errno, $errfile, $errline);
        default:
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}

/**
 * Wrapper voor exception_error_handler() met aparte exceptions voor
 * indexerrors, undefined properties en niet gevonden bestanden.
 */
function exception_error_handler_plus(
    int $errno,
    string $errstr,
    string $errfile,
    int $errline,
): bool {
    try {
        return exception_error_handler(
            $errno,
            $errstr,
            $errfile,
            $errline,
        );
    } catch (WarningErrorException $e) {
        if (\str_contains($errstr, 'No such file or directory')) {
            throw new FilesystemException($errstr, 0, $errno, $errfile, $errline);
        } elseif (\str_starts_with($e->getMessage(), 'Undefined array key')) {
            throw new IndexException($e->getMessage(), 0, $errno, $errfile, $errline);
        } elseif (\str_starts_with($e->getMessage(), 'Undefined property: ')) {
            throw new UndefinedPropertyException($e->getMessage(), 0, $errno, $errfile, $errline);
        } else {
            throw $e;
        }
    } catch (NoticeErrorException $e) {
        if (
            \str_starts_with($e->getMessage(), 'Undefined index: ')
            || \str_starts_with($e->getMessage(), 'Undefined offset: ')
        ) {
            throw new IndexException($e->getMessage(), 0, $errno, $errfile, $errline);
        } elseif (\str_starts_with($e->getMessage(), 'Trying to get property ')) {
            throw new UndefinedPropertyException($e->getMessage(), 0, $errno, $errfile, $errline);
        } elseif (\str_starts_with($e->getMessage(), 'Undefined property: ')) {
            throw new UndefinedPropertyException($e->getMessage(), 0, $errno, $errfile, $errline);
        } else {
            throw $e;
        }
    }
}

/**
 * Zet een string om in een object.
 * Zet de nu ingestelde tijdzone in het object.
 *
 * @param $in Invoer
 *
 * @throws NullException Als de invoer leeg is.
 * @throws GLDException Als de invoer geen geldige datumtijd is.
 */
function parse_datetime(string $in): \DateTime
{
    if (\strlen($in) === 0) {
        throw new NullException();
    }
    try {
        $dt = new \DateTime($in);
    } catch (\Throwable $e) {
        throw new GLDException($e->getMessage(), $e->getCode(), $e);
    }
    $dt->setTimezone(get_tijdzone());
    return $dt;
}

/**
 * Geeft de tijdzone van de server.
 *
 * @return \DateTimeZone
 */
function get_tijdzone(): \DateTimeZone
{
    return new \DateTimeZone(\date_default_timezone_get());
}

/**
 * Formateert een naam voor weergave in de URL.
 *
 * @param $str Invoer.
 *
 * @return string Uitvoer.
 */
function maak_url_slug(string $str): string
{
    $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $clean = preg_replace('/[^a-zA-Z0-9\/_|+ -]/', '', $clean);
    $clean = \strtolower(\trim($clean, '-'));
    $clean = preg_replace('/[\/_|+ -]+/', '-', $clean);
    return $clean;
}

/**
 * Genereert een willekeurige reeks tekens.
 *
 * @param $tekens Regex-deel met de tekens die in de string voor mogen
 * komen.
 * @param int<1, max> $lengte Lengte van het resultaat.
 * @param $deel Deel van de gegenereerde string. Alleen voor recursieve
 * aanroep.
 */
function genereer_random_string(string $tekens, int $lengte, string $deel = ''): string
{
    $regex = \sprintf(
        '~[^%s]~',
        $tekens
    );
    $string = \substr($deel . \preg_replace($regex, '', \random_bytes($lengte)), 0, $lengte);
    if (\strlen($string) === $lengte) {
        return $string;
    } else {
        return genereer_random_string($tekens, $lengte, $string);
    }
}

/**
 * Filtert een variabele en maakt er een int of float van als dat kan.
 *
 * @param $invoer
 *
 * @throws TypeException Als de waarde niet omgezet kan worden.
 */
function filter_number(mixed $waarde): int|float
{
    try {
        return filter_int($waarde);
    } catch (TypeException) {
        return filter_float($waarde);
    }
}

function filter_int(mixed $value): int
{
    $filtered = \filter_var($value, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE);
    if ($filtered === null) {
        // @phpstan-ignore cast.string
        TypeException::throw_unexpected('int', (string)$value);
    }
    return (int)$filtered;
}

function filter_bool(mixed $value, bool $nullable = false): bool
{
    $filtered = \filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);
    if ($filtered === null) {
        // @phpstan-ignore cast.string
        TypeException::throw_unexpected('bool', (string)$value);
    }
    return (bool)$filtered;
}

function filter_float(mixed $value): float
{
    $filtered = \filter_var($value, \FILTER_VALIDATE_FLOAT, \FILTER_NULL_ON_FAILURE);
    if ($filtered === null) {
        // @phpstan-ignore cast.string
        TypeException::throw_unexpected('float', (string)$value);
    }
    return (float)$filtered;
}

/**
 * @template TKey
 *
 * @param array<TKey, mixed> $array
 *
 * @return array<TKey, int>
 */
function filter_int_array(array $array): array
{
    return \array_map(filter_int(...), $array);
}

/**
 * @template TKey
 *
 * @param array<TKey, mixed> $array
 *
 * @return array<TKey, bool>
 */
function filter_bool_array(array $array): array
{
    return \array_map(filter_bool(...), $array);
}

/**
 * @template TKey
 *
 * @param array<TKey, mixed> $array
 *
 * @return array<TKey, float>
 */
function filter_float_array(array $array): array
{
    return \array_map(filter_float(...), $array);
}

/**
 * Geeft een afgeronde leesbare tekstrepresentatie van een aantal bytes.
 *
 * @param $bytes
 */
function format_bytes_afgerond(int|float $bytes): string
{
    $log = \log($bytes, 2);
    if ($log >= 80) {
        return \sprintf(
            '%.0fYiB',
            \round($bytes / 2 ** 80)
        );
    } elseif ($log >= 70) {
        return \sprintf(
            '%.0fZiB',
            \round($bytes / 2 ** 70)
        );
    } elseif ($log >= 60) {
        return \sprintf(
            '%.0fEiB',
            \round($bytes / 2 ** 60)
        );
    } elseif ($log >= 50) {
        return \sprintf(
            '%.0fPiB',
            \round($bytes / 2 ** 50)
        );
    } elseif ($log >= 40) {
        return \sprintf(
            '%.0fTiB',
            \round($bytes / 2 ** 40)
        );
    } elseif ($log >= 30) {
        return \sprintf(
            '%.0fGiB',
            \round($bytes / 2 ** 30)
        );
    } elseif ($log >= 20) {
        return \sprintf(
            '%.0fMiB',
            \round($bytes / 2 ** 20)
        );
    } elseif ($log >= 10) {
        return \sprintf(
            '%.0fKiB',
            \round($bytes / 2 ** 10)
        );
    } else {
        return \sprintf(
            '%.0fB',
            $bytes
        );
    }
}

/**
 * Geeft een lijst met bestanden in een map volgens deze criteria:
 * - Alleen reguliere bestanden (geen mappen)
 * - Bestandsnaam begint niet met een punt.
 * - Bestand wordt niet beschreven door een ander proces.
 *
 * @param $map
 * @param $max Geef niet meer dan dit aantal bestanden terug (standaard
 * zonder beperking)
 *
 * @return list<string> Lijst met bestanden, volledig pad.
 */
function ingest_scandir(Log $log, string $map, ?int $max = null): array
{
    $respons = [];
    foreach (scandir($map) as $bestandsnaam) {
        $pad = path_join($map, $bestandsnaam);
        if (!is_bestand_klaar($pad)) {
            $log->notice(
                'Bestand "%s" wordt geschreven door een ander proces (het bestand blijft staan)',
                $bestandsnaam
            );
            continue;
        }
        if (
            \is_file($pad)
            && $bestandsnaam !== ''
            && $bestandsnaam[0] !== '.'
            && \pathinfo($pad, \PATHINFO_EXTENSION) !== 'filepart'
        ) {
            $respons[] = $pad;
        }
        if (isset($max) && \count($respons) === $max) {
            return $respons;
        }
    }
    return $respons;
}

/**
 * Formatteert een string volgens de locale-instelling.
 *
 * @see https://unicode-org.github.io/icu/userguide/format_parse/datetime/
 *
 * @param $dt Datumtijd.
 * @param $pattern Patroon.
 * @param $dateType Datumnotatie (optioneel)
 * @param $timeType Tijdnotatie (optioneel)
 */
function strftime_intl(
    \DateTime $dt,
    ?string $pattern = null,
    int $dateType = \IntlDateFormatter::NONE,
    int $timeType = \IntlDateFormatter::NONE,
): string {
    $formatter = new \IntlDateFormatter(
        null,
        dateType: $dateType,
        timeType: $timeType,
        pattern: $pattern,
    );
    $res = $formatter->format($dt);
    if ($res === false) {
        throw SafeException::createFromPhpError();
    }
    return $res;
}

/**
 * Zet een mimetype om in een extensie.
 * https://stackoverflow.com/questions/16511021/convert-mime-type-to-file-extension-php
 *
 * @param $mime
 *
 * @throws GLDException Als het mimetype niet in de lijst staat.
 */
function mime2ext(string $mime): string
{
    $mime_map = [
        'video/3gpp2'                                                               => '3g2',
        'video/3gp'                                                                 => '3gp',
        'video/3gpp'                                                                => '3gp',
        'application/x-compressed'                                                  => '7zip',
        'audio/x-acc'                                                               => 'aac',
        'audio/ac3'                                                                 => 'ac3',
        'application/postscript'                                                    => 'ai',
        'audio/x-aiff'                                                              => 'aif',
        'audio/aiff'                                                                => 'aif',
        'audio/x-au'                                                                => 'au',
        'video/x-msvideo'                                                           => 'avi',
        'video/msvideo'                                                             => 'avi',
        'video/avi'                                                                 => 'avi',
        'application/x-troff-msvideo'                                               => 'avi',
        'application/macbinary'                                                     => 'bin',
        'application/mac-binary'                                                    => 'bin',
        'application/x-binary'                                                      => 'bin',
        'application/x-macbinary'                                                   => 'bin',
        'image/bmp'                                                                 => 'bmp',
        'image/x-bmp'                                                               => 'bmp',
        'image/x-bitmap'                                                            => 'bmp',
        'image/x-xbitmap'                                                           => 'bmp',
        'image/x-win-bitmap'                                                        => 'bmp',
        'image/x-windows-bmp'                                                       => 'bmp',
        'image/ms-bmp'                                                              => 'bmp',
        'image/x-ms-bmp'                                                            => 'bmp',
        'application/bmp'                                                           => 'bmp',
        'application/x-bmp'                                                         => 'bmp',
        'application/x-win-bitmap'                                                  => 'bmp',
        'application/cdr'                                                           => 'cdr',
        'application/coreldraw'                                                     => 'cdr',
        'application/x-cdr'                                                         => 'cdr',
        'application/x-coreldraw'                                                   => 'cdr',
        'image/cdr'                                                                 => 'cdr',
        'image/x-cdr'                                                               => 'cdr',
        'zz-application/zz-winassoc-cdr'                                            => 'cdr',
        'application/mac-compactpro'                                                => 'cpt',
        'application/pkix-crl'                                                      => 'crl',
        'application/pkcs-crl'                                                      => 'crl',
        'application/x-x509-ca-cert'                                                => 'crt',
        'application/pkix-cert'                                                     => 'crt',
        'text/css'                                                                  => 'css',
        'text/x-comma-separated-values'                                             => 'csv',
        'text/comma-separated-values'                                               => 'csv',
        'application/vnd.msexcel'                                                   => 'csv',
        'application/x-director'                                                    => 'dcr',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
        'application/x-dvi'                                                         => 'dvi',
        'message/rfc822'                                                            => 'eml',
        'application/x-msdownload'                                                  => 'exe',
        'video/x-f4v'                                                               => 'f4v',
        'audio/x-flac'                                                              => 'flac',
        'video/x-flv'                                                               => 'flv',
        'image/gif'                                                                 => 'gif',
        'application/gpg-keys'                                                      => 'gpg',
        'application/x-gtar'                                                        => 'gtar',
        'application/x-gzip'                                                        => 'gzip',
        'application/mac-binhex40'                                                  => 'hqx',
        'application/mac-binhex'                                                    => 'hqx',
        'application/x-binhex40'                                                    => 'hqx',
        'application/x-mac-binhex40'                                                => 'hqx',
        'text/html'                                                                 => 'html',
        'image/x-icon'                                                              => 'ico',
        'image/x-ico'                                                               => 'ico',
        'image/vnd.microsoft.icon'                                                  => 'ico',
        'text/calendar'                                                             => 'ics',
        'application/java-archive'                                                  => 'jar',
        'application/x-java-application'                                            => 'jar',
        'application/x-jar'                                                         => 'jar',
        'image/jp2'                                                                 => 'jp2',
        'video/mj2'                                                                 => 'jp2',
        'image/jpx'                                                                 => 'jp2',
        'image/jpm'                                                                 => 'jp2',
        'image/jpeg'                                                                => 'jpeg',
        'image/pjpeg'                                                               => 'jpeg',
        'application/x-javascript'                                                  => 'js',
        'application/json'                                                          => 'json',
        'text/json'                                                                 => 'json',
        'application/vnd.google-earth.kml+xml'                                      => 'kml',
        'application/vnd.google-earth.kmz'                                          => 'kmz',
        'text/x-log'                                                                => 'log',
        'audio/x-m4a'                                                               => 'm4a',
        'audio/mp4'                                                                 => 'm4a',
        'application/vnd.mpegurl'                                                   => 'm4u',
        'audio/midi'                                                                => 'mid',
        'application/vnd.mif'                                                       => 'mif',
        'video/quicktime'                                                           => 'mov',
        'video/x-sgi-movie'                                                         => 'movie',
        'audio/mpeg'                                                                => 'mp3',
        'audio/mpg'                                                                 => 'mp3',
        'audio/mpeg3'                                                               => 'mp3',
        'audio/mp3'                                                                 => 'mp3',
        'video/mp4'                                                                 => 'mp4',
        'video/mpeg'                                                                => 'mpeg',
        'application/mxf'                                                           => 'mxf',
        'application/oda'                                                           => 'oda',
        'audio/ogg'                                                                 => 'ogg',
        'video/ogg'                                                                 => 'ogg',
        'application/ogg'                                                           => 'ogg',
        'font/otf'                                                                  => 'otf',
        'application/x-pkcs10'                                                      => 'p10',
        'application/pkcs10'                                                        => 'p10',
        'application/x-pkcs12'                                                      => 'p12',
        'application/x-pkcs7-signature'                                             => 'p7a',
        'application/pkcs7-mime'                                                    => 'p7c',
        'application/x-pkcs7-mime'                                                  => 'p7c',
        'application/x-pkcs7-certreqresp'                                           => 'p7r',
        'application/pkcs7-signature'                                               => 'p7s',
        'application/pdf'                                                           => 'pdf',
        'application/octet-stream'                                                  => 'pdf',
        'application/x-x509-user-cert'                                              => 'pem',
        'application/x-pem-file'                                                    => 'pem',
        'application/pgp'                                                           => 'pgp',
        'application/x-httpd-php'                                                   => 'php',
        'application/php'                                                           => 'php',
        'application/x-php'                                                         => 'php',
        'text/php'                                                                  => 'php',
        'text/x-php'                                                                => 'php',
        'application/x-httpd-php-source'                                            => 'php',
        'image/png'                                                                 => 'png',
        'image/x-png'                                                               => 'png',
        'application/powerpoint'                                                    => 'ppt',
        'application/vnd.ms-powerpoint'                                             => 'ppt',
        'application/vnd.ms-office'                                                 => 'ppt',
        'application/msword'                                                        => 'doc',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/x-photoshop'                                                   => 'psd',
        'image/vnd.adobe.photoshop'                                                 => 'psd',
        'audio/x-realaudio'                                                         => 'ra',
        'audio/x-pn-realaudio'                                                      => 'ram',
        'application/x-rar'                                                         => 'rar',
        'application/rar'                                                           => 'rar',
        'application/x-rar-compressed'                                              => 'rar',
        'audio/x-pn-realaudio-plugin'                                               => 'rpm',
        'application/x-pkcs7'                                                       => 'rsa',
        'text/rtf'                                                                  => 'rtf',
        'text/richtext'                                                             => 'rtx',
        'video/vnd.rn-realvideo'                                                    => 'rv',
        'application/x-stuffit'                                                     => 'sit',
        'application/smil'                                                          => 'smil',
        'text/srt'                                                                  => 'srt',
        'image/svg+xml'                                                             => 'svg',
        'application/x-shockwave-flash'                                             => 'swf',
        'application/x-tar'                                                         => 'tar',
        'application/x-gzip-compressed'                                             => 'tgz',
        'image/tiff'                                                                => 'tiff',
        'font/ttf'                                                                  => 'ttf',
        'text/plain'                                                                => 'txt',
        'text/x-vcard'                                                              => 'vcf',
        'application/videolan'                                                      => 'vlc',
        'text/vtt'                                                                  => 'vtt',
        'audio/x-wav'                                                               => 'wav',
        'audio/wave'                                                                => 'wav',
        'audio/wav'                                                                 => 'wav',
        'application/wbxml'                                                         => 'wbxml',
        'video/webm'                                                                => 'webm',
        'image/webp'                                                                => 'webp',
        'audio/x-ms-wma'                                                            => 'wma',
        'application/wmlc'                                                          => 'wmlc',
        'video/x-ms-wmv'                                                            => 'wmv',
        'video/x-ms-asf'                                                            => 'wmv',
        'font/woff'                                                                 => 'woff',
        'font/woff2'                                                                => 'woff2',
        'application/xhtml+xml'                                                     => 'xhtml',
        'application/excel'                                                         => 'xl',
        'application/msexcel'                                                       => 'xls',
        'application/x-msexcel'                                                     => 'xls',
        'application/x-ms-excel'                                                    => 'xls',
        'application/x-excel'                                                       => 'xls',
        'application/x-dos_ms_excel'                                                => 'xls',
        'application/xls'                                                           => 'xls',
        'application/x-xls'                                                         => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
        'application/vnd.ms-excel'                                                  => 'xlsx',
        'application/xml'                                                           => 'xml',
        'text/xml'                                                                  => 'xml',
        'text/xsl'                                                                  => 'xsl',
        'application/xspf+xml'                                                      => 'xspf',
        'application/x-compress'                                                    => 'z',
        'application/x-zip'                                                         => 'zip',
        'application/zip'                                                           => 'zip',
        'application/x-zip-compressed'                                              => 'zip',
        'application/s-compressed'                                                  => 'zip',
        'multipart/x-zip'                                                           => 'zip',
        'text/x-scriptzsh'                                                          => 'zsh',
    ];
    if (\array_key_exists($mime, $mime_map)) {
        return $mime_map[$mime];
    } else {
        throw new GLDException("Onbekend mimetype: \"{$mime}\"");
    }
}

/**
 * Bepaalt de juiste extensie van een bestand aan de hand van het bestandstype.
 * Geeft de originele extensie uit de naam terug als het niet aan de hand van
 * het type kan worden bepaald.
 *
 * @param $pad Pad naar het bestand.
 */
function get_extensie_uit_type(string $pad): string
{
    try {
        return mime2ext(mime_content_type($pad));
    } catch (GLDException $e) {
        return \pathinfo($pad, \PATHINFO_EXTENSION);
    }
}

/**
 * Verandert de tijdzone van een DateTime in de lokale tijdzone.
 */
function set_lokale_tijdzone(\DateTime $dt): \DateTime
{
    return $dt->setTimezone(new \DateTimeZone(\date_default_timezone_get()));
}

/**
 * Zet een string- of nullwaarde om in een bool- of nullwaarde.
 */
function bool_null_val(?string $value): ?bool
{
    return isset($value) ? (bool)$value : null;
}

/**
 * Zet een string- of nullwaarde om in een int- of nullwaarde.
 */
function int_null_val(?string $value): ?int
{
    return isset($value) ? (int)$value : null;
}

/**
 * Zet een string- of nullwaarde om in een float- of nullwaarde.
 */
function float_null_val(?string $value): ?float
{
    return isset($value) ? \floatval($value) : null;
}

/**
 * Maak een object met ...$args als parameters voor de constructor.
 * Als $args[0] null is wordt er geen object gemaakt maar null teruggegeven.
 *
 * @template T
 *
 * @param class-string<T> $class_name
 *
 * @return ?T
 */
function obj_null_val(string $class_name, mixed ...$args)
{
    if (!isset($args[0])) {
        return null;
    } else {
        return new $class_name(...$args);
    }
}

/**
 * Sluit een stream zonder foutmeldingen.
 *
 * @param ?resource $stream
 */
function fclose_safe($stream = null): void
{
    if (isset($stream)) {
        try {
            \fclose($stream);
        } catch (\Throwable) {
        }
    }
}

/**
 * Verwijdert een bestand zonder foutmeldingen.
 */
function unlink_safe(?string $filename = null): void
{
    if (isset($filename)) {
        try {
            \unlink($filename);
        } catch (\Throwable) {
        }
    }
}

/**
 * Voert \readline() uit maar geeft een default terug als de gebruikersinvoer
 * leeg is.
 *
 * @param $prompt Prompt aan de gebruiker
 * @param $default Antwoord als er niets wordt ingevuld.
 * @param $mag_leeg Bij false wordt een error gegenereerd als de invoer leeg is.
 *
 * @return string Antwoord.
 *
 * @throws GLDException Als de gebruikersinvoer leeg is, $mag_leeg false is en
 * $default leeg is.
 */
function readline_met_default(
    string $prompt,
    string $default = '',
    bool $mag_leeg = true
): string {
    if ($default !== '') {
        $prompt = "{$prompt} [{$default}]: ";
    } else {
        $prompt = "{$prompt}: ";
    }
    $ans = \readline($prompt);
    if ($ans === false) {
        $ans = '';
    }
    $ans = \trim($ans);
    if ($ans === '') {
        $ans = $default;
    }
    if ($ans === '' && !$mag_leeg) {
        throw new GLDException('De invoer mag niet leeg zijn.');
    }
    return $ans;
}

/**
 * Get the path to the currently running script.
 */
function get_running_script_path(): string
{
    return get_included_files()[0];
}

/**
 * Geeft het mimetype van een blob.
 *
 * @throws \gldstdlib\safe\FilesystemException
 * @throws \gldstdlib\safe\FileinfoException
 */
function blob_mime_content_type(string $data): string
{
    $stream = fopen('php://temp', 'r+');
    try {
        fwrite($stream, $data);
        rewind($stream);
        return mime_content_type($stream);
    } finally {
        fclose($stream);
    }
}

/**
 * Trimfunctie die ook non-breaking spaces verwijdert.
 *
 * @throws \gldstdlib\safe\PcreException
 */
function ltrim_nbsp(string $string): string
{
    return preg_replace('~^[\s\x00]+~u', '', $string);
}

/**
 * Trimfunctie die ook non-breaking spaces verwijdert.
 *
 * @throws \gldstdlib\safe\PcreException
 */
function rtrim_nbsp(string $string): string
{
    return preg_replace('~[\s\x00]+$~u', '', $string);
}

/**
 * Trimfunctie die ook non-breaking spaces verwijdert.
 *
 * @throws \gldstdlib\safe\PcreException
 */
function trim_nbsp(string $string): string
{
    return preg_replace('~^[\s\x00]+|[\s\x00]+$~u', '', $string);
}

/**
 * Geeft aan of een url bereikbaar is.
 *
 * @return bool True als de URL een HTTP ok status heeft, anders false.
 */
function url_bestaat(string $url): bool
{
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            \CURLOPT_URL => $url,
            \CURLOPT_HEADER => true,
            \CURLOPT_NOBODY => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_exec($ch);
        $http_code = \curl_getinfo($ch)['http_code'];
        \curl_close($ch);
        return $http_code >= 200 && $http_code < 300;
    } catch (\gldstdlib\safe\CurlException) {
        return false;
    }
}

/**
 * Serializeert URL parameters als querystring en voegt die toe aan een URL.
 *
 * @param $url URL. Deze mag nog geen querystring bevatten.
 * @param array<string, mixed> $url_params URL-parameters als associatieve
 * array.
 */
function maak_url_met_querystring(string $url, array $url_params): string
{
    if (\count($url_params) === 0) {
        return $url;
    } else {
        $querystring = \http_build_query($url_params, '', '&', \PHP_QUERY_RFC3986);
        return "{$url}?{$querystring}";
    }
}

/**
 * Haalt de inhoud van een webresource op als string.
 *
 * @param $url
 * @param $timeout Maximale timeout (standaard 30)
 *
 * @throws \GuzzleHttp\Exception\GuzzleException
 */
function guzzle_get_contents(string $url, int $timeout = 30): string
{
    $client = new \GuzzleHttp\Client(
        [
            'timeout'  => $timeout,
            'verify' => false,
        ]
    );
    $response = $client->get($url);
    return $response->getBody()->getContents();
}

/**
 * Haalt de grootte op van een bestand op een URL.
 *
 * @param $url
 * @param $timeout Maximale timeout (standaard 30)
 *
 * @return float Grootte in bytes.
 *
 * @throws \GuzzleHttp\Exception\GuzzleException
 * @throws GLDException als de Content-Length header ontbreekt.
 * @throws GLDException als de Content-Length header ongeldig is.
 */
function guzzle_get_size(string $url, int $timeout = 30): float
{
    $client = new \GuzzleHttp\Client(
        [
            'timeout'  => $timeout,
            'verify' => false,
        ]
    );
    $response = $client->head($url);
    $lengths = $response->getHeader('Content-Length');
    if (\count($lengths) === 0) {
        throw new GLDException("Header Content-Length ontbreekt in {$url}");
    }
    $length = $lengths[0];
    if (!\is_numeric($length)) {
        throw new GLDException("Ongeldige Content-Length header in {$url}: {$length}");
    }
    return (float)$length;
}

/**
 * Verwerkt een telefoonnummer zoals die door gebruikers kan worden aangeleverd.
 *
 * Leestekens worden weggefilterd.
 *
 * Lokale nummers worden internationaal gemaakt.
 *
 * Nederlands nummers worden gecheckt op geldige prefix en lengte.
 *
 * @param string $telefoonnummer Invoer.
 *
 * @return string Het geparste telefoonnummer.
 *
 * @throws GLDException Bij een ongeldig telefoonnummer.
 */
function parse_telefoonnummer(string $telefoonnummer): string
{
    if (\strlen($telefoonnummer) < 4) {
        throw new GLDException("Ongeldig telefoonnummer: \"{$telefoonnummer}\"");
    }
    $telefoonnummer = preg_replace('~[^+0-9]~', '', $telefoonnummer);
    if (\substr($telefoonnummer, 0, 2) === '00') {
        // Handmatige uitbelcode
        $telefoonnummer = '+' . \substr($telefoonnummer, 2);
    }
    if ($telefoonnummer[0] === '0') {
        // Lokaal Nederlands nummer
        $telefoonnummer = '+31' . \substr($telefoonnummer, 1);
    }
    if (\substr($telefoonnummer, 0, 4) === '+310') {
        // Onnodige nul na landcode
        $telefoonnummer = '+31' . \substr($telefoonnummer, 4);
    }
    if (\substr($telefoonnummer, 0, 3) === '+31') {
        // Nederlands nummer
        if (preg_match('~^\+31([1-5][0-9]|6[1-5]|68|7[0-9]|80|82|84|85|87|88|91)[0-9]{7}$~', $telefoonnummer) === 1) {
            return $telefoonnummer;
        } else {
            throw  new GLDException("Ongeldig telefoonnummer: \"{$telefoonnummer}\"");
        }
    }
    if (preg_match('~^\+[0-9]{8,15}$~', $telefoonnummer) === 1) {
            // Internationaal nummer
            return $telefoonnummer;
    } else {
        throw new GLDException("Ongeldig telefoonnummer: \"{$telefoonnummer}\"");
    }
}

/**
 * Plaatst (non breaking) spaties in een Nederlands internationaal telefoonnummer
 * voor de leesbaarheid.
 *
 * @param $telefoonnummer Origineel telefoonnummer
 */
function format_telefoonnummer(string $telefoonnummer): string
{
    $patronen = [
        // Viercijferige netnummers
        '~^(\+31)((?:11|16|17|18|22|25|29|31|32|34|41|44|47|47|48|49|51|52|54|56|57|59|67|80|90)[0-9])([0-9]{2})([0-9]{2})([0-9]{2})$~',
        // Eencijferige netnummers
        '~^(\+31)(6)([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})$~',
        // Tweecijferige netnummers
        '~^(\+31)([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$~',
    ];
    foreach ($patronen as $patroon) {
        if (preg_match($patroon, $telefoonnummer, $m)) {
            array_shift($m);
            return implode(' ', $m);
        }
    }
    return $telefoonnummer;
}

/**
 * Geeft het IP-adres van de indiener van een formulier.
 *
 * @return ?string IP-adres. Dit kan ook een ipv6-adres zijn. Null als er geen
 * adres kan worden gevonden.
 */
function get_client_ip(): ?string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']) && \is_string($_SERVER['HTTP_CLIENT_IP'])) {   //check ip from share internet
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && \is_string($_SERVER['HTTP_X_FORWARDED_FOR'])) {   //to check ip is pass from proxy
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR']) && \is_string($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    } else {
        return null;
    }
}

/**
 * Vervang voor Windows ongeldige tekens in bestandsnamen.
 *
 * Gebruik dit alleen voor basenames, niet voor volledige paden, want de slash
 * wordt vervangen.
 */
function vervang_bestandsnaam_tekens(string $string): string
{
    return str_replace(
        ['<', '>', ':', '"', '/', '\\', '|', '?', '*'],
        ['﹤', '﹥', 'ː', '“', '⁄', '∖', '⼁', '﹖', '﹡'],
        $string
    );
}
