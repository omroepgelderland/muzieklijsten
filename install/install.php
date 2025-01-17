<?php

namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();
$container = get_di_container();
$container->call(install(...));
