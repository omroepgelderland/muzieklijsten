<?php
/**
 * Dupliceert een lijst met alle gekoppelde nummer en velden.
 * Gebruik dit om een lijst die periodiek gebruikt wordt te resetten.
 * De bestaande lijst is de actieve; de gedupliceerde lijst is het archief.
 * De oude resultaten worden dan bewaard in een archieflijst.
 */

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

DB::disableAutocommit();

$origineel_id = filter_var(readline('id van de te dupliceren lijst: '), FILTER_VALIDATE_INT);
$nieuwe_naam = filter_var(readline('nieuwe naam: '));

$lijst = new Lijst($origineel_id);
$lijst->dupliceer($nieuwe_naam);

DB::commit();
