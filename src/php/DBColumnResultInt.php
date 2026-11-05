<?php

declare(strict_types=1);

namespace muzieklijsten;

use function gldstdlib\fs;
use function gldstdlib\ns;

/**
 * @extends DBColumnResult<int>
 */
class DBColumnResultInt extends DBColumnResult
{
    /**
     * @return \Traversable<int, int>
     */
    public function getIterator(): \Traversable
    {
        while (( $r = $this->res->fetch_row() ) !== null) {
            yield (int)fs(filter_var(ns($r[0] ?? null), \FILTER_VALIDATE_INT));
        }
    }
}
