#!/usr/bin/env php
<?php

/**
 * Anonimiseert persoonlijke data van stemmers.
 */

declare(strict_types=1);

/** @disregard P1105 */
namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();
$container = get_di_container();
$container->call(anonimiseer_stemmers(...));
