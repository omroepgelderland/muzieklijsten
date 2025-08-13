<?php

declare(strict_types=1);

namespace gldstdlib\exception;

class TypeException extends GLDException
{
    /**
     * @throws self
     */
    public static function throw_unexpected(string $expected, string $actual): never
    {
        throw new self("Verkeerd type. Verwachtte {$expected} maar was {$actual}");
    }

    /**
     * @throw self
     */
    public static function throw_invalid(string $type): never
    {
        throw new self("Ongeldig type: {$type}");
    }
}
