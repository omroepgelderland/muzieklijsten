<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * Converts string from from_encoding
 * to to_encoding.
 *
 * @param $from_encoding The current encoding used to interpret string.
 * @param $to_encoding The desired encoding of the result.
 *
 * If the string //TRANSLIT is appended to
 * to_encoding, then transliteration is activated. This
 * means that when a character can't be represented in the target charset,
 * it may be approximated through one or several similarly looking
 * characters. If the string //IGNORE is appended,
 * characters that cannot be represented in the target charset are silently
 * discarded. Otherwise, E_NOTICE is generated and the function
 * will return FALSE.
 *
 * If and how //TRANSLIT works exactly depends on the
 * system's iconv() implementation (cf. ICONV_IMPL).
 * Some implementations are known to ignore //TRANSLIT,
 * so the conversion is likely to fail for characters which are illegal for
 * the to_encoding.
 * @param $string The string to be converted.
 *
 * @return string Returns the converted string.
 *
 * @throws IconvException
 */
function iconv(string $from_encoding, string $to_encoding, string $string): string
{
    \error_clear_last();
    $safeResult = \iconv($from_encoding, $to_encoding, $string);
    if ($safeResult === false) {
        throw IconvException::createFromPhpError();
    }
    return $safeResult;
}
