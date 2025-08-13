<?php

declare(strict_types=1);

namespace gldstdlib;

/**
 * Houdt de voortgang van een S3 multipart upload bij.
 *
 * @phpstan-type Callback callable(int, int): void
 */
class S3MultipartProgress
{
    private int $bytes;
    private int $totaal;
    /** @var Callback */
    private $callback;

    /**
     * @param $totaal Grootte van het volledige bestand in bytes.
     * @param Callback $callback. Deze functie wordt aangeroepen voor de upload
     * van elke chunk. De parameters waarmee de callback wordt aangeroepen zijn
     * de totaalgrootte en de het tot nu toe geüploade aantal bytes.
     */
    public function __construct(int $totaal, callable $callback)
    {
        $this->bytes = 0;
        $this->totaal = $totaal;
        $this->callback = $callback;
    }

    /**
     * Geef deze functie als parameter voor before_upload.
     *
     * @param \Aws\Command<string, mixed> $command
     * @param $chunk_number
     */
    public function before_upload(\Aws\Command $command, int $chunk_number): void
    {
        $chunk_size = filter_int($command['ContentLength']);
        $this->bytes += $chunk_size;
        ($this->callback)($this->totaal, $this->bytes);
        \gc_collect_cycles();
    }
}
