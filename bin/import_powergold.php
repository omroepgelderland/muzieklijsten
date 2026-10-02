<?php

/**
 * Importscript voor nummers uit Powergold.
 */

namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();
$container = get_di_container();
$container->call(import_powergold(...), [
    'filename' => $argv[1],
]);
