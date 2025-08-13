<?php

/**
 * Importscript voor nummers uit Powergold.
 *
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();
$container = get_di_container();
$container->call(PowergoldImporter::controller(...), [
    'filename' => $argv[1],
]);
