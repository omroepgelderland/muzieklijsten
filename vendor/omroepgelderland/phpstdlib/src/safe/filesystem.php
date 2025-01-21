<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * This function is similar to file, except that
 * file_get_contents returns the file in a
 * string, starting at the specified offset
 * up to length bytes. On failure,
 * file_get_contents will return FALSE.
 *
 * file_get_contents is the preferred way to read the
 * contents of a file into a string.  It will use memory mapping techniques if
 * supported by your OS to enhance performance.
 *
 * @param $filename Name of the file to read.
 * @param $use_include_path The FILE_USE_INCLUDE_PATH constant can be used
 * to trigger include path
 * search.
 * This is not possible if strict typing
 * is enabled, since FILE_USE_INCLUDE_PATH is an
 * int. Use TRUE instead.
 * @param resource|null $context A valid context resource created with
 * stream_context_create. If you don't need to use a
 * custom context, you can skip this parameter by NULL.
 * @param $offset The offset where the reading starts on the original stream.
 * Negative offsets count from the end of the stream.
 *
 * Seeking (offset) is not supported with remote files.
 * Attempting to seek on non-local files may work with small offsets, but this
 * is unpredictable because it works on the buffered stream.
 * @param int<0, max> $length Maximum length of data read. The default is to read until end
 * of file is reached. Note that this parameter is applied to the
 * stream processed by the filters.
 *
 * @return string The function returns the read data.
 *
 * @throws FilesystemException
 */
function file_get_contents(
    string $filename,
    bool $use_include_path = false,
    $context = null,
    int $offset = 0,
    ?int $length = null,
): string {
    \error_clear_last();
    if ($length !== null) {
        $safeResult = \file_get_contents($filename, $use_include_path, $context, $offset, $length);
    } elseif ($offset !== 0) {
        $safeResult = \file_get_contents($filename, $use_include_path, $context, $offset);
    } elseif ($context !== null) {
        $safeResult = \file_get_contents($filename, $use_include_path, $context);
    } else {
        $safeResult = \file_get_contents($filename, $use_include_path);
    }
    if ($safeResult === false) {
        throw FilesystemException::createFromPhpError();
    }
    return $safeResult;
}

/**
 * This function is identical to calling fopen,
 * fwrite and fclose successively
 * to write data to a file.
 *
 * If filename does not exist, the file is created.
 * Otherwise, the existing file is overwritten, unless the
 * FILE_APPEND flag is set.
 *
 * @param $filename Path to the file where to write the data.
 * @param $data The data to write. Can be either a string, an
 * array or a stream resource.
 *
 * If data is a stream resource, the
 * remaining buffer of that stream will be copied to the specified file.
 * This is similar with using stream_copy_to_stream.
 *
 * You can also specify the data parameter as a single
 * dimension array. This is equivalent to
 * file_put_contents($filename, implode('', $array)).
 * @param $flags The value of flags can be any combination of
 * the following flags, joined with the binary OR (|)
 * operator.
 *
 *
 * Available flags
 *
 *
 *
 * Flag
 * Description
 *
 *
 *
 *
 *
 * FILE_USE_INCLUDE_PATH
 *
 *
 * Search for filename in the include directory.
 * See include_path for more
 * information.
 *
 *
 *
 *
 * FILE_APPEND
 *
 *
 * If file filename already exists, append
 * the data to the file instead of overwriting it.
 *
 *
 *
 *
 * LOCK_EX
 *
 *
 * Acquire an exclusive lock on the file while proceeding to the
 * writing. In other words, a flock call happens
 * between the fopen call and the
 * fwrite call. This is not identical to an
 * fopen call with mode "x".
 *
 * @param resource|null $context A valid context resource created with
 * stream_context_create.
 *
 * @return int This function returns the number of bytes that were written to the file.
 *
 * @throws FilesystemException
 */
function file_put_contents(
    string $filename,
    mixed $data,
    int $flags = 0,
    $context = null
): int {
    \error_clear_last();
    if ($context !== null) {
        $safeResult = \file_put_contents($filename, $data, $flags, $context);
    } else {
        $safeResult = \file_put_contents($filename, $data, $flags);
    }
    if ($safeResult === false) {
        throw FilesystemException::createFromPhpError();
    }
    return $safeResult;
}

/**
 * Gets the size for the given file.
 *
 * @param $filename Path to the file.
 *
 * @return int Returns the size of the file in bytes.
 *
 * @throws FilesystemException in case of an error
 *
 * @phpstan-impure
 */
function filesize(string $filename): int
{
    \error_clear_last();
    $safeResult = \filesize($filename);
    if ($safeResult === false) {
        throw FilesystemException::createFromPhpError();
    }
    return $safeResult;
}
