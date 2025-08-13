<?php

declare(strict_types=1);

namespace gldstdlib;

use Monolog\Level;

/**
 * @phpstan-import-type MailInfoType from Log
 */
class LogFactory
{
    /**
     * @param ?MailInfoType $mail_info
     */
    public function __construct(
        private Level $level,
        private string $log_dir,
        private ?string $filename,
        private ?array $mail_info,
    ) {
    }

    /**
     * @param ?MailInfoType $mail_info
     */
    public function create_log(
        ?Level $level = null,
        ?string $log_dir = null,
        ?string $filename = null,
        ?array $mail_info = null,
    ): Log {
        return new Log(
            $level ?? $this->level,
            $log_dir ?? $this->log_dir,
            $filename ?? $this->filename,
            $mail_info ?? $this->mail_info,
        );
    }
}
