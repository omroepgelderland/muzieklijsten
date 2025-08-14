<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * Execute the given cURL session.
 *
 * This function should be called after initializing a cURL session and all
 * the options for the session are set.
 *
 * @param \CurlHandle $handle A cURL handle returned by curl_init.
 *
 * @return string|null On success, this function flushes the result directly to
 * the stdout and returns NULL.
 *
 * However, if the CURLOPT_RETURNTRANSFER option is set, it will return the
 * result on success.
 *
 * @throws CurlException
 */
function curl_exec(\CurlHandle $handle): ?string
{
    \error_clear_last();
    $safeResult = \curl_exec($handle);
    if ($safeResult === false) {
        throw CurlException::createFromPhpError($handle);
    } elseif ($safeResult === true) {
        return null;
    } else {
        return $safeResult;
    }
}

/**
 * Gets information about the last transfer.
 *
 * @param \CurlHandle $handle A cURL handle returned by
 * curl_init.
 * @param int|null $option One of the CURLINFO_* constants.
 *
 * @return ($option is null ? array<string, mixed> : mixed ) If option is given,
 * returns its value.
 * Otherwise, returns an associative array with the following elements
 * (which correspond to option):
 *
 * "url"
 *
 * "content_type"
 *
 * "http_code"
 *
 * "header_size"
 *
 * "request_size"
 *
 * "filetime"
 *
 * "ssl_verify_result"
 *
 * "redirect_count"
 *
 * "total_time"
 *
 * "namelookup_time"
 *
 * "connect_time"
 *
 * "pretransfer_time"
 *
 * "size_upload"
 *
 * "size_download"
 *
 * "speed_download"
 *
 * "speed_upload"
 *
 * "download_content_length"
 *
 * "upload_content_length"
 *
 * "starttransfer_time"
 *
 * "redirect_time"
 *
 * "certinfo"
 *
 * "primary_ip"
 *
 * "primary_port"
 *
 * "local_ip"
 *
 * "local_port"
 *
 * "redirect_url"
 *
 * "request_header" (This is only set if the CURLINFO_HEADER_OUT
 * is set by a previous call to curl_setopt)
 *
 * Note that private data is not included in the associative array and must be
 * retrieved individually with the CURLINFO_PRIVATE option.
 *
 * @throws CurlException
 */
function curl_getinfo(\CurlHandle $handle, ?int $option = null): mixed
{
    \error_clear_last();
    if ($option !== null) {
        $safeResult = \curl_getinfo($handle, $option);
    } else {
        $safeResult = \curl_getinfo($handle);
    }
    if ($safeResult === false) {
        throw CurlException::createFromPhpError($handle);
    }
    return $safeResult;
}

/**
 * Initializes a new session and returns a cURL handle.
 *
 * @param string|null $url If provided, the CURLOPT_URL option will be set
 * to its value. This can be set manually using the
 * curl_setopt function.
 *
 * The file protocol is disabled by cURL if
 * open_basedir is set.
 *
 * @return \CurlHandle Returns a cURL handle on success, FALSE on errors.
 *
 * @throws CurlException
 */
function curl_init(?string $url = null): \CurlHandle
{
    \error_clear_last();
    if ($url !== null) {
        $safeResult = \curl_init($url);
    } else {
        $safeResult = \curl_init();
    }
    if ($safeResult === false) {
        throw CurlException::createFromPhpError();
    }
    return $safeResult;
}

/**
 * Sets an option on the given cURL session handle.
 *
 * @param \CurlHandle $handle A cURL handle returned by
 * curl_init.
 * @param int $option The CURLOPT_* option to set.
 * @param mixed $value The value to be set on option.
 * See the description of the
 * CURLOPT_* constants
 * for details on the type of values each constant expects.
 *
 * @throws CurlException
 */
function curl_setopt(\CurlHandle $handle, int $option, mixed $value): void
{
    \error_clear_last();
    $safeResult = \curl_setopt($handle, $option, $value);
    if ($safeResult === false) {
        throw CurlException::createFromPhpError($handle);
    }
}

/**
 * Sets multiple options for a cURL session. This function is useful for setting
 * a large number of cURL options without repetitively calling curl_setopt().
 *
 * @param $handle A cURL handle returned by curl_init().
 * @param array<int, mixed> $options An array specifying which options to set
 * and their values. The keys should be valid curl_setopt() constants or their
 * integer equivalents.
 *
 * @throws CurlException
 */
function curl_setopt_array(\CurlHandle $handle, array $options): void
{
    \error_clear_last();
    $safeResult = \curl_setopt_array($handle, $options);
    if ($safeResult === false) {
        throw CurlException::createFromPhpError($handle);
    }
}
