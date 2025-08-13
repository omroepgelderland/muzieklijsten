<?php

declare(strict_types=1);

namespace gldstdlib\safe;

class JsonException extends SafeException
{
    public static function createFromPhpError(): static
    {
        return new static(\json_last_error_msg(), \json_last_error());
    }
}
