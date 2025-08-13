<?php

declare(strict_types=1);

namespace gldstdlib\safe;

class CurlException extends SafeException
{
    public static function createFromPhpError(
        ?\CurlHandle $ch = null
    ): static {
        return new static($ch ? \curl_error($ch) : '', $ch ? \curl_errno($ch) : 0);
    }
}
