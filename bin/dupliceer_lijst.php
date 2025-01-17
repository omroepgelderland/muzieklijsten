<?php

/**
 * Interactief script.
 * Dupliceert een lijst met alle gekoppelde nummer en velden.
 * Gebruik dit om een lijst die periodiek gebruikt wordt te resetten.
 * De bestaande lijst is de actieve; de gedupliceerde lijst is het archief.
 * De oude resultaten worden dan bewaard in een archieflijst.
 *
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

require_once __DIR__ . '/../vendor/autoload.php';

set_env();
$container = get_di_container();
$container->call(dupliceer_lijst(...));
