<?php

declare(strict_types=1);

namespace gldstdlib\safe;

class SafeException extends \ErrorException
{
    final public function __construct(
        string $message = "",
        int $code = 0,
        int $severity = \E_ERROR,
        ?string $filename = null,
        ?int $line = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $severity, $filename, $line, $previous);
    }

    public static function createFromPhpError(): static
    {
        $error = \error_get_last();
        return new static($error['message'] ?? 'An error occurred', 0, $error['type'] ?? 1);
    }
}
