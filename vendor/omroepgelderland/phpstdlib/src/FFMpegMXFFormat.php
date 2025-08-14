<?php

declare(strict_types=1);

namespace gldstdlib;

/**
 * MXF format voor php-ffmpeg.
 */
class FFMpegMXFFormat extends \FFMpeg\Format\Video\DefaultVideo
{
    public function __construct(
        string $audio_codec = 'pcm_s16le',
        string $video_codec = 'mpeg2video'
    ) {
        $this->setVideoCodec($video_codec);
        $this->setAudioCodec($audio_codec);
    }

    public function supportBFrames(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     *
     * @inheritdoc
     */
    public function getAvailableAudioCodecs()
    {
        return [
            'copy',
            'pcm_s16le',
            'pcm_s24le',
        ];
    }

    /**
     * @return list<string>
     *
     * @inheritdoc
     */
    public function getAvailableVideoCodecs()
    {
        return [
            'copy',
            'jpeg2000',
            'libopenjpeg',
            'libx264',
            'libx264rgb',
            'mpeg2video',
            'prores_aw',
            'prores',
        ];
    }
}
