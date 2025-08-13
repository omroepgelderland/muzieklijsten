<?php

declare(strict_types=1);

namespace gldstdlib;

use gldstdlib\exception\GLDException;
use gldstdlib\exception\MXFOmzetterException;

/**
 * Class voor het omzetten van een MXF-bestand met twee mono audiostreams voor
 * links en rechts naar een stereokanaal.
 *
 * @phpstan-type ProgressCallbackType callable(
 *     \FFMpeg\Media\AdvancedMedia,
 *     FFMpegMXFFormat,
 *     float
 * ): void
 */
class MXFOmzetter
{
    private string $audio_codec_name;
    private ?bool $is_dubbel_mono_mxf = null;

    /**
     * @param $pad Pad naar de video.
     */
    public function __construct(
        private string $pad
    ) {
    }

    /**
     * Checkt of de video voldoet aan de voorwaarden voor omzetting. Dit zijn:
     *
     * - Er is één videokanaal.
     * - Er zijn twee of meer audiokanalen.
     * - De eerste twee audiokanalen zijn mono.
     */
    public function is_dubbel_mono_mxf(): bool
    {
        return $this->is_dubbel_mono_mxf ??= $this->is_dubbel_mono_mxf_check();
    }

    private function is_dubbel_mono_mxf_check(): bool
    {
        $ffprobe = \FFMpeg\FFProbe::create();
        $streams = $ffprobe->streams($this->pad);
        $video_streams = $streams->videos();
        if ($video_streams->count() !== 1) {
            return false;
        }
        $audio_streams = $streams->audios();
        if ($audio_streams->count() < 2) {
            return false;
        }
        // $video_stream = $video_streams->first();
        // if ($video_stream->get('codec_name') !== 'mpeg2video') {
        //     return false;
        // }
        [$audio_links, $audio_rechts] = $audio_streams->all();
        if (
            !(
            $audio_links instanceof \FFMpeg\FFProbe\DataMapping\Stream
            && $audio_rechts instanceof \FFMpeg\FFProbe\DataMapping\Stream
            )
        ) {
            throw new GLDException('ffmpeg stream verwacht');
        }
        $audio_codec_name = $audio_links->get('codec_name');
        if (!\is_string($audio_codec_name)) {
            throw new GLDException('string verwacht');
        }
        $this->audio_codec_name = $audio_codec_name;
        if ($audio_links->get('channels') !== 1 || $audio_rechts->get('channels') !== 1) {
            return false;
        }
        return true;
    }

    /**
     * Converteert de audio naar één stereokanaal. Het eerste kanaal in het
     * bronbestand wordt het linkerkanaal, het tweede het rechterkanaal. Als er
     * nog meer audiokanalen zijn dan worden die niet meegenomen in het
     * resultaat.
     *
     * @param $doel Pad waar het omgezette bestand wordt opgeslagen.
     * @param ProgressCallbackType $progress_callback Optionele functie voor het
     *   bijhouden van de voortgang van de omzetting.
     *   Parameters van de callback zijn:
     *   \FFMpeg\Media\AdvancedMedia $video,
     *   \io_nimbus\FFMpegMXFFormat $format,
     *   float $percentage
     *
     * @throws MXFOmzetterException Als het invoerbestand niet het juiste formaat
     * heeft.
     *
     * ffmpeg
     *     -y
     *     -i mxf.mxf
     *     -filter_complex "[0:1][0:2] amerge=inputs=2[a]"
     *     -c:v copy
     *     -c:a pcm_s24le
     *     -map 0:0
     *     -map "[a]"
     *     stereo.mkv
     * ffmpeg
     *     -y
     *     -i mxf.mxf
     *     -filter_complex "[0:1][0:2] join=inputs=2:channel_layout=stereo:map=0.0-FL|1.0-FR[a]"
     *     -c:v copy
     *     -c:a pcm_s24le
     *     -map 0:0
     *     -map "[a]"
     *     stereo.mkv
     */
    public function omzetten(string $doel, ?callable $progress_callback = null): void
    {
        if (!$this->is_dubbel_mono_mxf()) {
            throw new MXFOmzetterException("{$this->pad} is geen MXF-bestand met aparte monokanalen");
        }
        $ffmpeg = \FFMpeg\FFMpeg::create();
        $advanced_media = $ffmpeg->openAdvanced([$this->pad]);
        $advanced_media->filters()->custom(
            '[0:1][0:2]',
            'join=inputs=2:channel_layout=stereo:map=0.0-FL|1.0-FR',
            '[a]'
        );
        $format = $this->get_format($progress_callback);
        if (isset($progress_callback)) {
            // callback aanroepen bij 0%, want ffmpeg doet het niet bij start
            // en einde.
            $progress_callback($advanced_media, $format, (float)0);
        }
        $advanced_media->map(['0:0', '[a]'], $format, $doel);
        try {
            $advanced_media->save();
        } catch (\Throwable $e) {
            // Opruimen bij errors.
            unlink_safe($doel);
            throw $e;
        }
        if (isset($progress_callback)) {
            // callback aanroepen bij 100%, want ffmpeg doet het niet bij start
            // en einde.
            $progress_callback($advanced_media, $format, (float)100);
        }
    }

    /**
     * @param ?ProgressCallbackType $progress_callback
     */
    private function get_format(?callable $progress_callback = null): FFMpegMXFFormat
    {
        $format = new FFMpegMXFFormat($this->audio_codec_name, 'copy');
        if (isset($progress_callback)) {
            $format->on('progress', $progress_callback);
        }
        return $format;
    }
}
