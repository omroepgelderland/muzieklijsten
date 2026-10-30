#!/usr/bin/env php
<?php

/**
 * Regenereert alle vgl_titel en vgl_artiest velden in de nummerstabel.
 */

declare(strict_types=1);

/** @disregard P1105 */
namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();

$container = get_di_container();
$container->call(vgl_bijwerken(...));
