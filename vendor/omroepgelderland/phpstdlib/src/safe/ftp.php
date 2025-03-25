<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * ftp_connect opens an FTP connection to the
 * specified hostname.
 *
 * @param $hostname The FTP server address. This parameter shouldn't have any trailing
 * slashes and shouldn't be prefixed with ftp://.
 * @param $port This parameter specifies an alternate port to connect to. If it is
 * omitted or set to zero, then the default FTP port, 21, will be used.
 * @param $timeout This parameter specifies the timeout in seconds for all subsequent network operations.
 * If omitted, the default value is 90 seconds. The timeout can be changed and
 * queried at any time with ftp_set_option and
 * ftp_get_option.
 *
 * @return \FTP\Connection Returns an FTP\Connection instance.
 *
 * @throws FtpException
 */
function ftp_connect(string $hostname, int $port = 21, int $timeout = 90): \FTP\Connection
{
    \error_clear_last();
    $safeResult = \ftp_connect($hostname, $port, $timeout);
    if ($safeResult === false) {
        throw FtpException::createFromPhpError();
    }
    return $safeResult;
}

/**
 * ftp_ssl_connect opens an explicit SSL-FTP connection to the
 * specified hostname. That implies that
 * ftp_ssl_connect will succeed even if the server is not
 * configured for SSL-FTP. Only when ftp_login is called, the client will send the
 * appropriate AUTH FTP command, so ftp_login will fail.
 * The connection established by ftp_ssl_connect will not do
 * peer-certificate verification.
 *
 * @param $hostname The FTP server address. This parameter shouldn't have any trailing
 * slashes and shouldn't be prefixed with ftp://.
 * @param $port This parameter specifies an alternate port to connect to. If it is
 * omitted or set to zero, then the default FTP port, 21, will be used.
 * @param $timeout This parameter specifies the timeout for all subsequent network operations.
 * If omitted, the default value is 90 seconds. The timeout can be changed and
 * queried at any time with ftp_set_option and
 * ftp_get_option.
 *
 * @return \FTP\Connection Returns an FTP\Connection instance.
 *
 * @throws FtpException
 */
function ftp_ssl_connect(string $hostname, int $port = 21, int $timeout = 90): \FTP\Connection
{
    \error_clear_last();
    $safeResult = \ftp_ssl_connect($hostname, $port, $timeout);
    if ($safeResult === false) {
        throw FtpException::createFromPhpError();
    }
    return $safeResult;
}
