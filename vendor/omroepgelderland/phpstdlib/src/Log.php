<?php

declare(strict_types=1);

namespace gldstdlib;

use Monolog\Handler\FilterHandler;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * Logger.
 *
 * @phpstan-type MailInfoType array{
 *     to: string|list<string>,
 *     from: string,
 *     project: string
 * }
 */
class Log
{
    private Logger $logger;
    private string $filename;
    private Level $level;
    private string $log_dir;
    /** @var MailInfoType */
    private ?array $mail_info;

    /**
     * @param $level Alleen gebeurtenissen van dit level en hoger worden gelogd.
     * @param $log_dir Map waar de logs worden opgeslagen.
     * @param $filename Optionele bestandsnaam. Als deze null is dan wordt de
     * naam van het nu draaiende script gebruikt.
     * @param MailInfoType $mail_info Parameters voor de mail logger. Null
     * betekent niet mailen.
     */
    final public function __construct(
        Level $level,
        string $log_dir,
        ?string $filename = null,
        ?array $mail_info = null,
    ) {
        $this->level = $level;
        $this->log_dir = $log_dir;
        $this->mail_info = $mail_info;
        $this->maak_logger(
            $filename ?? \pathinfo(get_running_script_path())['filename']
        );
    }

    /**
     * Initialiseert logger. Roep deze ook aan als de bestandsnaam is veranderd.
     */
    private function maak_logger(string $filename): void
    {
        $this->filename = $filename;
        $this->logger = new Logger($this->filename);
        $this->add_handlers();
    }

    /**
     * Regelt uitvoerstreams.
     */
    private function add_handlers(): void
    {
        // Output naar commandline
        if (is_cli()) {
            $handler = new StreamHandler('php://output', $this->level);
            $this->logger->pushHandler($handler);
        }

        // Bestandslog
        // Altijd naar bestand schrijven, ook als scripts interactief wordt uitgevoerd.
        $file_handler = new StreamHandler($this->get_path(), $this->level, filePermission:0664);
        $this->logger->pushHandler($file_handler);

        // Logs per errortype
        $notice_filter_handler = new FilterHandler(
            new StreamHandler(
                $this->get_path('errors5_notice'),
                Level::Notice,
                filePermission:0664
            ),
            [Level::Notice]
        );
        $this->logger->pushHandler($notice_filter_handler);
        $warn_filter_handler = new FilterHandler(
            new StreamHandler(
                $this->get_path('errors4_warn'),
                Level::Notice,
                filePermission:0664
            ),
            [Level::Notice]
        );
        $this->logger->pushHandler($warn_filter_handler);
        $error_filter_handler = new FilterHandler(
            new StreamHandler(
                $this->get_path('errors3_err'),
                Level::Notice,
                filePermission:0664
            ),
            [Level::Notice]
        );
        $this->logger->pushHandler($error_filter_handler);
        $crit_filter_handler = new FilterHandler(
            new StreamHandler(
                $this->get_path('errors2_crit'),
                Level::Notice,
                filePermission:0664
            ),
            [Level::Notice]
        );
        $this->logger->pushHandler($crit_filter_handler);
        $alert_filter_handler = new FilterHandler(
            new StreamHandler(
                $this->get_path('errors1_alert'),
                Level::Notice,
                filePermission:0664
            ),
            [Level::Notice]
        );
        $this->logger->pushHandler($alert_filter_handler);
        $notice_filter_handler = new FilterHandler(
            new StreamHandler(
                $this->get_path('errors0_emerg'),
                Level::Notice,
                filePermission:0664
            ),
            [Level::Notice]
        );

        $mail_handler = $this->get_mail_handler();
        if (isset($mail_handler)) {
            $this->logger->pushHandler($mail_handler);
        }
    }

    /**
     * Geeft het pad naar het logbestand.
     *
     * @param $filename Optionale bestandsnaam voor logbestanden die afwijken
     * van de filename class property. (zonder extensie).
     */
    private function get_path(?string $filename = null): string
    {
        return path_join(
            $this->log_dir,
            \sprintf(
                '%s_%s.log',
                $filename ?? $this->filename,
                (new \DateTime())->format('Y-m-d')
            )
        );
    }

    /**
     * Stelt een andere naam in.
     *
     * @param $filename Bestandsnaam zonder extensie.
     */
    public function set_filename(string $filename): void
    {
        $this->maak_logger($filename);
    }

    public function emerg(string $msg_format, string|int|float ...$msg_values): void
    {
        $this->write(Level::Emergency, $msg_format, ...$msg_values);
    }

    public function alert(string $msg_format, string|int|float ...$msg_values): void
    {
        $this->write(Level::Alert, $msg_format, ...$msg_values);
    }

    public function crit(string $msg_format, string|int|float ...$msg_values): void
    {
        $this->write(Level::Critical, $msg_format, ...$msg_values);
    }

    public function err(string $msg_format, string|int|float ...$msg_values): void
    {
        $this->write(Level::Error, $msg_format, ...$msg_values);
    }

    public function warn(string $msg_format, string|int|float ...$msg_values): void
    {
        $this->write(Level::Warning, $msg_format, ...$msg_values);
    }

    public function notice(string $msg_format, string|int|float ...$msg_values): void
    {
        $this->write(Level::Notice, $msg_format, ...$msg_values);
    }

    public function info(string $msg_format, string|int|float ...$msg_values): void
    {
        $this->write(Level::Info, $msg_format, ...$msg_values);
    }

    public function debug(string $msg_format, string|int|float ...$msg_values): void
    {
        $this->write(Level::Debug, $msg_format, ...$msg_values);
    }

    private function write(
        Level $priority,
        string $msg_format,
        string|int|float ...$msg_values
    ): void {
        if (\count($msg_values) === 0) {
            $message = $msg_format;
        } else {
            $message = \sprintf($msg_format, ...$msg_values);
        }
        $this->logger->log($priority, $message);
        // Elke regel apart. Goed voor bestanden maar niet voor mails.
        // foreach ( \explode("\n", $message) as $regel ) {
        //     self::get_logger()->log($priority, $regel);
        // }
    }

    /**
     * Mail writer voor kritieke fouten.
     */
    private function get_mail_handler(): ?NativeMailerHandler
    {
        $info = $this->mail_info;
        if ($info === null) {
            return null;
        }
        return new NativeMailerHandler(
            $info['to'],
            "{$info['project']} error {$this->filename}",
            $info['from'],
            Level::Critical
        );
    }
}
