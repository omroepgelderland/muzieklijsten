<?php
/**
 * Importscript voor nummers uit Powergold.
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

function multiKeyExists( array $array, string $key ): bool {
    if ( array_key_exists($key, $array) ) {
        return true;
    }
    foreach ( $array as $v ) {
        if ( !is_array($v) ) {
            continue;
        }
        if ( array_key_exists($key, $v) ) {
            return true;
        }
    }
    return false;
}

/**
 * Converteert een waarde uit Excel
 * @param string $waarde
 * @param boolean $null_als_leeg Geef null terug als de string leeg is.
 */
function filter_cell_string( string $waarde, bool $null_als_leeg ) {
    $res = trim(mb_convert_encoding($waarde, 'utf-8', 'windows-1252'));
    if ( $null_als_leeg && $waarde === '' ) {
        $res = null;
    }
    return $res;
}

function filter_cell_int( string $waarde ): ?int {
    $string = filter_cell_string($waarde, false);
    $res = filter_var($string, FILTER_VALIDATE_INT);
    if ( $res === false ) {
        throw new Muzieklijsten_Exception("Ongeldige integer: {$string}");
    }
    return $res;
}

function verwerk_kolomtitels( array $rij ): array {
    $keys = [];
    foreach( $rij as $i => $cel ) {
        $waarde = filter_cell_string($cel, true);
        if ( $waarde === 'Miscellaneous' ) {
            $keys['muziek_id'] = $i;
        }
        elseif ( $waarde === 'Title' ) {
            $keys['titel'] = $i;
        }
        elseif ( $waarde === 'Artist(s)' ) {
            $keys['artiest'] = $i;
        }
        elseif ( $waarde === 'Year' ) {
            $keys['jaar'] = $i;
        }
        elseif ( $waarde === 'Category' ) {
            $keys['categorie'] = $i;
        }
        elseif ( $waarde === 'Folder' ) {
            $keys['map'] = $i;
        }
        elseif ( $waarde === 'Opener' ) {
            $keys['opener'] = $i;
        }
    }
    $verplichte_kolommen = [
        'muziek_id',
        'titel',
        'artiest',
        'jaar'
    ];
    $ontbrekende_kolommen = array_diff($verplichte_kolommen, array_keys($keys));
    if ( count($ontbrekende_kolommen) > 0 ) {
        $ontbrekende_kolommen_str = implode(', ', $ontbrekende_kolommen);
        throw new Muzieklijsten_Exception("De volgende verplichte velden ontbreken in de sheet: {$ontbrekende_kolommen_str}");
    }
    return $keys;
}

function get_cel( array $rij, array $keys, string $key, string $type, bool $null_als_leeg ) {
    try {
        if ( $type === 'string' ) {
            return filter_cell_string($rij[$keys[$key]], $null_als_leeg);
        }
        if ( $type === 'int' ) {
            return filter_cell_int($rij[$keys[$key]]);
        }
    } catch ( IndexException $e ) {
        return $null_als_leeg
            ? null
            : '';
    }
}

$excel = new PhpExcelReader;
$excel->read($argv[1]);

$data = $excel->sheets[0]['cells'];

$keys = verwerk_kolomtitels($data[7]);

$data = array_slice($data, 2);

$db = DB::getDB();
$query = <<<EOT
INSERT INTO nummers (muziek_id, titel, artiest, jaar, categorie, map, opener)
VALUES (?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    muziek_id = ?,
    titel = ?,
    artiest = ?,
    jaar = ?,
    categorie = ?,
    map = ?,
    opener = ?
EOT;
$toevoegen = $db->prepare($query);
if ( $toevoegen === false ) {
    throw new SQLException('Prepared statement mislukt: '.$db->error, $db->errno);
}
$res = $toevoegen->bind_param('sssississsissi',
    $muziek_id,
    $titel,
    $artiest,
    $jaar,
    $categorie,
    $map,
    $opener,
    $muziek_id,
    $titel,
    $artiest,
    $jaar,
    $categorie,
    $map,
    $opener
);
if ( $res === false ) {
    throw new SQLException('Prepared statement mislukt: '.$toevoegen->error, $toevoegen->errno);
}
$aantal_toegevoegd = 0;
$aantal_bijgewerkt = 0;
foreach ( $data as $key => $rij ) {
    $titel = get_cel($rij, $keys, 'titel', 'string', true);
    if ( $titel === 'Title' || $titel === null ) {
        // Rij zonder nummer of herhaalde kolomtitels.
        continue;
    }
    $muziek_id = get_cel($rij, $keys, 'muziek_id', 'string', true);
    if ( $muziek_id !== null && preg_match('~[^0-9a-zA-Z\-]~', $muziek_id) === 1 ) {
        // Ongeldige ID's
        $muziek_id = null;
    }
    $artiest = get_cel($rij, $keys, 'artiest', 'string', false);
    $jaar = get_cel($rij, $keys, 'jaar', 'int', true);
    if ( $jaar === 0 ) {
        $jaar = null;
    }
    $categorie = get_cel($rij, $keys, 'categorie', 'string', true);
    $map = get_cel($rij, $keys, 'map', 'string', true);
    $opener_str = get_cel($rij, $keys, 'opener', 'string', false);
    $opener = strtolower($opener_str) === 'yes';

    if ( $muziek_id === null && $jaar === null ) {
        // Bij nummers zonder muziek ID en jaar handmatig checken op dubbelingen
        // omdat jaar NULL niet wordt meegenomen bij de unique primary key.
        $e_titel = DB::escape_string($titel);
        $e_artiest = DB::escape_string($artiest);
        $query = <<<EOT
        SELECT id
        FROM nummers
        WHERE
            muziek_id IS NULL
            AND titel = "{$e_titel}"
            AND artiest = "{$e_artiest}"
            AND jaar IS NULL
        EOT;
        try {
            $nummer_id = DB::selectSingle($query);
            $aantal_bijgewerkt += DB::updateMulti('nummers', [
                'categorie' => $categorie,
                'map' => $map,
                'opener' => $opener
            ], "id = {$nummer_id}");
        } catch ( SQLException $e ) {
            DB::insertMulti('nummers', [
                'titel' => $titel,
                'artiest' => $artiest,
                'categorie' => $categorie,
                'map' => $map,
                'opener' => $opener
            ]);
            $aantal_toegevoegd++;
        }
    } else {
        // Unique keys maken het verschil tussen insert en update.
        $res = $toevoegen->execute();
        if ( $res === false ) {
            throw new SQLException('Query mislukt: '.$toevoegen->error, $toevoegen->errno);
        }
        if ( $db->affected_rows === 1 ) {
            // insert
            $aantal_toegevoegd++;
        } elseif ( $db->affected_rows === 2 ) {
            // update
            $aantal_bijgewerkt += $db->affected_rows;
        }
    }

}
$toevoegen->close();
echo "{$aantal_toegevoegd} nummers geïmporteerd en {$aantal_bijgewerkt} bijgewerkt.\n";
