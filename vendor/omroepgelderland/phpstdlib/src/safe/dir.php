<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * Returns an array of files and directories from the
 * directory.
 *
 * @param $directory The directory that will be scanned.
 * @param 0|1|2 $sorting_order By default, the sorted order is alphabetical in ascending order.  If
 * the optional sorting_order is set to
 * SCANDIR_SORT_DESCENDING, then the sort order is
 * alphabetical in descending order. If it is set to
 * SCANDIR_SORT_NONE then the result is unsorted.
 * @param resource|null $context For a description of the context parameter,
 * refer to the streams section of
 * the manual.
 *
 * @return list<string> Returns an array of filenames.
 *
 * @throws DirException If directory is not a directory.
 */
function scandir(string $directory, int $sorting_order = \SCANDIR_SORT_ASCENDING, $context = null): array
{
    \error_clear_last();
    if ($context !== null) {
        $safeResult = \scandir($directory, $sorting_order, $context);
    } else {
        $safeResult = \scandir($directory, $sorting_order);
    }
    if ($safeResult === false) {
        throw DirException::createFromPhpError();
    }
    return $safeResult;
}
