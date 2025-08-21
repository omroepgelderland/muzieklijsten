<?php

/**
 * Regenereert alle vgl_titel en vgl_artiest velden in de nummerstabel.
 */

namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();

$container = get_di_container();
$container->call(vgl_bijwerken(...));
