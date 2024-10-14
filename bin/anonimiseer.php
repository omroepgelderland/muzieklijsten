<?php
/**
 * Anonimiseert persoonlijke data van stemmers.
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

 namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

set_env();

DB::disableAutocommit();

$toen = (new \DateTime())->sub(new \DateInterval('P3M'));
$query = <<<EOT
    SELECT id
    FROM stemmers
    WHERE
        is_geanonimiseerd = 0
        AND timestamp < "{$toen->format('Y-m-d H:i:s')}"
EOT;
foreach ( DB::selectObjectLijst($query, Stemmer::class) as $stemmer ) {
    $stemmer->anonimiseer();
}
verwijder_ongekoppelde_vrije_keuze_nummers();
verwijder_stemmers_zonder_stemmen();

DB::commit();
