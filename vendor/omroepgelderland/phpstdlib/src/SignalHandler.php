<?php

declare(strict_types=1);

namespace gldstdlib;

/**
 * Handler in voor OS exitsignalen.
 * Dit zorgt ervoor dat het uitgevoerde script niet direct stopt bij een
 * exitsignaal. Het script moet dit dan zelf afhandelen.
 * Wordt gebruikt bij daemons.
 */
class SignalHandler
{
    private bool $exit;

    public function __construct(
        private Log $log
    ) {
        $this->exit = false;
    }

    /**
     * Geeft aan dat er een exitsignaal uit het OS is ontvangen en slaat dit op.
     *
     * @param $signo The signal being handled
     * @param $siginfo If operating systems supports siginfo_t structures,
     * this will be an array of signal information dependent on the signal.
     */
    private function signal_handler(int $signo, mixed $siginfo): void
    {
        $this->log->info("Signaal {$signo} ontvangen.");
        $this->exit = true;
    }

    /**
     * Checkt of er een signaal is ontvangen dat het proces moet stoppen.
     *
     * @return bool True als er een exitstatus is.
     */
    public function check_exit(): bool
    {
        \pcntl_signal_dispatch();
        return $this->exit;
    }

    /**
     * Start de handler.
     */
    public function install(): void
    {
        \pcntl_signal(\SIGHUP, $this->signal_handler(...));
        \pcntl_signal(\SIGTERM, $this->signal_handler(...));
        \pcntl_signal(\SIGINT, $this->signal_handler(...));
    }

    /**
     * Sleepfunctie die onderbroken wordt wanneer er een exitstatus is.
     *
     * @param $seconds Aantal seconden sleep.
     */
    public function sleep(int $seconds): void
    {
        for ($i = 0; $i < $seconds; $i++) {
            if ($this->check_exit()) {
                return;
            }
            \sleep(1);
        }
    }
}
