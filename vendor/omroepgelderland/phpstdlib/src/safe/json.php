<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * Wrapper for json_decode that throws when an error occurs.
 *
 * @link http://www.php.net/manual/en/function.json-decode.php
 *
 * @param $json    JSON data to parse
 * @param $associative     When true, returned objects will be converted
 *                        into associative arrays.
 * @param int<1, max> $depth   User specified recursion depth.
 * @param $flags Bitmask of JSON decode options.
 *
 * @return mixed
 *
 * @throws JsonException if the JSON cannot be decoded.
 */
function json_decode(
    string $json,
    ?bool $associative = null,
    int $depth = 512,
    int $flags = 0
): mixed {
    \error_clear_last();
    $data = \json_decode($json, $associative, $depth, $flags);
    if (!($flags & \JSON_THROW_ON_ERROR) && \JSON_ERROR_NONE !== \json_last_error()) {
        throw JsonException::createFromPhpError();
    }
    return $data;
}

/**
 * Returns a string containing the JSON representation of the supplied
 * value.  If the parameter is an array or object,
 * it will be serialized recursively.
 *
 * If a value to be serialized is an object, then by default only publicly visible
 * properties will be included. Alternatively, a class may implement JsonSerializable
 * to control how its values are serialized to JSON.
 *
 * The encoding is affected by the supplied flags
 * and additionally the encoding of float values depends on the value of
 * serialize_precision.
 *
 * @param $value The value being encoded. Can be any type except
 * a resource.
 *
 * All string data must be UTF-8 encoded.
 *
 * PHP implements a superset of JSON as specified in the original
 * RFC 7159.
 * @param $flags Bitmask consisting of
 * JSON_FORCE_OBJECT,
 * JSON_HEX_QUOT,
 * JSON_HEX_TAG,
 * JSON_HEX_AMP,
 * JSON_HEX_APOS,
 * JSON_INVALID_UTF8_IGNORE,
 * JSON_INVALID_UTF8_SUBSTITUTE,
 * JSON_NUMERIC_CHECK,
 * JSON_PARTIAL_OUTPUT_ON_ERROR,
 * JSON_PRESERVE_ZERO_FRACTION,
 * JSON_PRETTY_PRINT,
 * JSON_UNESCAPED_LINE_TERMINATORS,
 * JSON_UNESCAPED_SLASHES,
 * JSON_UNESCAPED_UNICODE,
 * JSON_THROW_ON_ERROR.
 * The behaviour of these constants is described on the
 * JSON constants page.
 * @param int<1, max> $depth Set the maximum depth. Must be greater than zero.
 *
 * @return string Returns a JSON encoded string on success.
 *
 * @throws JsonException
 */
function json_encode(
    mixed $value,
    int $flags = 0,
    int $depth = 512
): string {
    \error_clear_last();
    $safeResult = \json_encode($value, $flags, $depth);
    if ($safeResult === false) {
        throw JsonException::createFromPhpError();
    }
    return $safeResult;
}
