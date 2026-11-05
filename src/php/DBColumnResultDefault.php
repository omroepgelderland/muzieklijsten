<?php

declare(strict_types=1);

namespace muzieklijsten;

/**
 * @extends DBColumnResult<string|null>
 */
class DBColumnResultDefault extends DBColumnResult
{
    /**
     * @return \Traversable<int, string|null>
     */
    public function getIterator(): \Traversable
    {
        while (( $r = $this->res->fetch_row() ) !== null) {
            yield $r[0] ?? null;
        }
    }
}
