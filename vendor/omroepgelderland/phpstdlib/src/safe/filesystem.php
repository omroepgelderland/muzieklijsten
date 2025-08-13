<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * The file pointed to by stream is closed.
 *
 * @param resource $stream The file pointer must be valid, and must point to a file successfully
 * opened by fopen or fsockopen.
 *
 * @throws FilesystemException
 */
function fclose($stream): void
{
    \error_clear_last();
    $safeResult = \fclose($stream);
    if ($safeResult === false) {
        throw FilesystemException::createFromPhpError();
    }
}

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

/**
 * fopen binds a named resource, specified by
 * filename, to a stream.
 *
 * @param string $filename If filename is of the form "scheme://...", it
 * is assumed to be a URL and PHP will search for a protocol handler
 * (also known as a wrapper) for that scheme. If no wrappers for that
 * protocol are registered, PHP will emit a notice to help you track
 * potential problems in your script and then continue as though
 * filename specifies a regular file.
 *
 * If PHP has decided that filename specifies
 * a local file, then it will try to open a stream on that file.
 * The file must be accessible to PHP, so you need to ensure that
 * the file access permissions allow this access.
 * If you have enabled
 * open_basedir further
 * restrictions may apply.
 *
 * If PHP has decided that filename specifies
 * a registered protocol, and that protocol is registered as a
 * network URL, PHP will check to make sure that
 * allow_url_fopen is
 * enabled. If it is switched off, PHP will emit a warning and
 * the fopen call will fail.
 *
 * The list of supported protocols can be found in . Some protocols (also referred to as
 * wrappers) support context
 * and/or php.ini options. Refer to the specific page for the
 * protocol in use for a list of options which can be set. (e.g.
 * php.ini value user_agent used by the
 * http wrapper).
 *
 * On the Windows platform, be careful to escape any backslashes
 * used in the path to the file, or use forward slashes.
 *
 * @param $mode The mode
 * parameter specifies the type of access you require to the stream.
 * It may be any of the following:
 *
 * A list of possible modes for fopen
 * using mode
 *
 * mode
 * Description
 *
 * 'r'
 *
 * Open for reading only; place the file pointer at the
 * beginning of the file.
 *
 * 'r+'
 *
 * Open for reading and writing; place the file pointer at
 * the beginning of the file.
 *
 * 'w'
 *
 * Open for writing only; place the file pointer at the
 * beginning of the file and truncate the file to zero length.
 * If the file does not exist, attempt to create it.
 *
 * 'w+'
 *
 * Open for reading and writing; otherwise it has the
 * same behavior as 'w'.
 *
 * 'a'
 *
 * Open for writing only; place the file pointer at the end of
 * the file. If the file does not exist, attempt to create it.
 * In this mode, fseek has no effect, writes are always appended.
 *
 * 'a+'
 *
 * Open for reading and writing; place the file pointer at
 * the end of the file. If the file does not exist, attempt to
 * create it. In this mode, fseek only affects
 * the reading position, writes are always appended.
 *
 * 'x'
 *
 * Create and open for writing only; place the file pointer at the
 * beginning of the file.  If the file already exists, the
 * fopen call will fail by returning FALSE and
 * generating an error of level E_WARNING.  If
 * the file does not exist, attempt to create it.  This is equivalent
 * to specifying O_EXCL|O_CREAT flags for the
 * underlying open(2) system call.
 *
 * 'x+'
 *
 * Create and open for reading and writing; otherwise it has the
 * same behavior as 'x'.
 *
 * 'c'
 *
 * Open the file for writing only. If the file does not exist, it is
 * created. If it exists, it is neither truncated (as opposed to
 * 'w'), nor the call to this function fails (as is
 * the case with 'x'). The file pointer is
 * positioned on the beginning of the file. This may be useful if it's
 * desired to get an advisory lock (see flock)
 * before attempting to modify the file, as using
 * 'w' could truncate the file before the lock
 * was obtained (if truncation is desired,
 * ftruncate can be used after the lock is
 * requested).
 *
 * 'c+'
 *
 * Open the file for reading and writing; otherwise it has the same
 * behavior as 'c'.
 *
 * 'e'
 *
 * Set close-on-exec flag on the opened file descriptor. Only
 * available in PHP compiled on POSIX.1-2008 conform systems.
 *
 * Different operating system families have different line-ending
 * conventions.  When you write a text file and want to insert a line
 * break, you need to use the correct line-ending character(s) for your
 * operating system.  Unix based systems use \n as the
 * line ending character, Windows based systems use \r\n
 * as the line ending characters and Macintosh based systems (Mac OS Classic) used
 * \r as the line ending character.
 *
 * If you use the wrong line ending characters when writing your files, you
 * might find that other applications that open those files will "look
 * funny".
 *
 * Windows offers a text-mode translation flag ('t')
 * which will transparently translate \n to
 * \r\n when working with the file.  In contrast, you
 * can also use 'b' to force binary mode, which will not
 * translate your data.  To use these flags, specify either
 * 'b' or 't' as the last character
 * of the mode parameter.
 *
 * The default translation mode is 'b'.
 * You can use the 't'
 * mode if you are working with plain-text files and you use
 * \n to delimit your line endings in your script, but
 * expect your files to be readable with applications such as old versions of notepad.  You
 * should use the 'b' in all other cases.
 *
 * If you specify the 't' flag when working with binary files, you
 * may experience strange problems with your data, including broken image
 * files and strange problems with \r\n characters.
 *
 * For portability, it is also strongly recommended that
 * you re-write code that uses or relies upon the 't'
 * mode so that it uses the correct line endings and
 * 'b' mode instead.
 * @param bool $use_include_path The optional third use_include_path parameter
 * can be set to TRUE if you want to search for the file in the
 * include_path, too.
 * @param resource|null $context A context stream
 * resource.
 * @return resource Returns a file pointer resource on success
 * @throws FilesystemException
 */
function fopen(
    string $filename,
    string $mode,
    bool $use_include_path = false,
    $context = null
) {
    \error_clear_last();
    if ($context !== null) {
        $safeResult = \fopen($filename, $mode, $use_include_path, $context);
    } else {
        $safeResult = \fopen($filename, $mode, $use_include_path);
    }
    if ($safeResult === false) {
        throw FilesystemException::createFromPhpError();
    }
    return $safeResult;
}

/**
 * fread reads up to
 * length bytes from the file pointer
 * referenced by stream. Reading stops as soon as one
 * of the following conditions is met:
 *
 * length bytes have been read
 *
 * EOF (end of file) is reached
 *
 * a packet becomes available or the
 * socket timeout occurs (for network streams)
 *
 * if the stream is read buffered and it does not represent a plain file, at
 * most one read of up to a number of bytes equal to the chunk size (usually
 * 8192) is made; depending on the previously buffered data, the size of the
 * returned data may be larger than the chunk size.
 *
 * @param resource $stream A file system pointer resource
 * that is typically created using fopen.
 * @param int<1, max> $length Up to length number of bytes read.
 * @return string Returns the read string.
 * @throws FilesystemException
 *
 */
function fread($stream, int $length): string
{
    \error_clear_last();
    $safeResult = \fread($stream, $length);
    if ($safeResult === false) {
        throw FilesystemException::createFromPhpError();
    }
    return $safeResult;
}

/**
 * @param resource $stream A file system pointer resource
 * that is typically created using fopen.
 * @param string $data The string that is to be written.
 * @param int<0, max>|null $length If length is an integer, writing will stop
 * after length bytes have been written or the
 * end of data is reached, whichever comes first.
 *
 * @throws FilesystemException
 */
function fwrite(
    $stream,
    string $data,
    ?int $length = null
): int {
    \error_clear_last();
    if ($length !== null) {
        $safeResult = \fwrite($stream, $data, $length);
    } else {
        $safeResult = \fwrite($stream, $data);
    }
    if ($safeResult === false) {
        throw FilesystemException::createFromPhpError();
    }
    return $safeResult;
}

/**
 * Sets the file position indicator for stream
 * to the beginning of the file stream.
 *
 * @param resource $stream The file pointer must be valid, and must point to a file
 * successfully opened by fopen.
 *
 * @throws FilesystemException
 */
function rewind($stream): void
{
    \error_clear_last();
    $safeResult = \rewind($stream);
    if ($safeResult === false) {
        throw FilesystemException::createFromPhpError();
    }
}
