<?php

/**
 * Anonimiseert persoonlijke data van stemmers.
 */

namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();
$container = get_di_container();
$container->call(anonimiseer_stemmers(...));
