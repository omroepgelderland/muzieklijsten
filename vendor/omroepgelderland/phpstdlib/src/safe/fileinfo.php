<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * Returns the MIME content type for a file as determined by using
 * information from the magic.mime file.
 *
 * @param resource|string $filename Path to the tested file.
 *
 * @return string Returns the content type in MIME format, like
 * text/plain or application/octet-stream.
 *
 * @throws FileinfoException
 */
function mime_content_type($filename): string
{
    \error_clear_last();
    $safeResult = \mime_content_type($filename);
    if ($safeResult === false) {
        throw FileinfoException::createFromPhpError();
    }
    return $safeResult;
}
