#!/usr/bin/env php
<?php

/**
 * Importscript voor nummers uit Powergold.
 */

declare(strict_types=1);

/** @disregard P1105 */
namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();
$container = get_di_container();
$container->call(import_powergold(...), [
    'filename' => $argv[1],
]);
