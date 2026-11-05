<?php

declare(strict_types=1);

namespace muzieklijsten;

/**
 * @template Value
 *
 * @implements \IteratorAggregate<int, Value>
 */
abstract class DBColumnResult implements \IteratorAggregate, \Countable
{
    protected readonly int $length;

    public function __construct(
        protected readonly \mysqli_result $res,
    ) {
        if (\bccomp((string)$res->num_rows, (string)\PHP_INT_MAX) > 0) {
            throw new \RuntimeException(\sprintf(
                'Aantal rijen overschrijdt PHP_INT_MAX. Aantal: %s',
                $res->num_rows,
            ));
        }
        $length = (int)$res->num_rows;
        if ($length < 0) {
            throw new \RuntimeException('Length cannot be negative');
        }
        $this->length = $length;
    }

    /**
     * @return \Traversable<int, Value>
     */
    abstract public function getIterator(): \Traversable;

    public function count(): int
    {
        return $this->length;
    }
}
