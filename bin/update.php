<?php
/**
 * Past wijzigingen toe in de structuur van de database bij versie-updates.
 * De versie van de database wordt in de tabel versie bijgehouden. Voor elk
 * versienummer is er een functie in de class DBUpdates. Bij een update worden
 * alle functies vanaf het huidige versienummer uitgevoerd. Zo kunnen er ook
 * meerdere versies tegelijk worden opgehoogd.
 * 
 * Bij crashes is de exitstatus 1, zodat de update kan worden teruggedraaid.
 * Het versienummer in de database wordt dan niet geüpdate.
 * 
 * Doe dit bij het maken van een nieuwe versie waarbij de databasestructuur verandert:
 * - Voeg een static functie toe aan de class DBUpdates met de naam update_[0-9]+
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

set_env();

try {
    db_update();
} catch ( \Throwable $e ) {
    Log::crit($e);
    exit(1);
}
