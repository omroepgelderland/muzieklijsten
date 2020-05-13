<?php

require_once __DIR__.'/include/include.php';

include 'excel_reader.php'; 

function multiKeyExists( Array $array, $key ) {
    if (array_key_exists($key, $array)) {
        return true;
    }
    foreach ($array as $k=>$v) {
        if (!is_array($v)) {
            continue;
        }
        if (array_key_exists($key, $v)) {
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
function get_cell_string( $waarde, $null_als_leeg ) {
	$res = trim(mb_convert_encoding($waarde, "UTF-8", "Windows-1252"));
	if ( $null_als_leeg && $waarde == '' ) {
		$res = null;
	}
	return $res;
}

if ( is_dev() ) {
	error_reporting(E_ALL & ~E_NOTICE);
}

$excel = new PhpExcelReader;
$excel->read('powergold.xls');

$data = $excel->sheets[0]["cells"];

$db = Muzieklijsten_Database::getDB();
$toevoegen = $db->prepare('INSERT INTO muzieklijst_nummers (muziek_id, titel, artiest, jaar, categorie, map, opener)
VALUES (?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
	muziek_id = ?,
	titel = ?,
	artiest = ?,
	jaar = ?,
	categorie = ?,
	map = ?,
	opener = ?');
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
foreach ($data as $key => $arr) {
	if ( (strlen($arr[9]) == 9 || strlen($arr[9]) == 10) && preg_match('/\s/',$arr[9]) == false)  {
		$artiest = get_cell_string($arr[2], false);
		$titel = get_cell_string($arr[5], false);
		$jaar = (int)$arr[8];
		if ( $jaar === 0 ) {
			$jaar = null;
		}
		$muziek_id = get_cell_string($arr[9], true);
		$categorie = get_cell_string($arr[11], true);
		$map = get_cell_string($arr[12], true);
		if ( strtolower(get_cell_string($arr[14], false)) == 'yes' ) {
			$opener = 1;
		} else {
			$opener = 0;
		}

//		printf("%s - %s (%d)\n", $artiest, $titel, $jaar);
		$res = $toevoegen->execute();
		if ( $res === false ) {
			throw new SQLException('Query mislukt: '.$toevoegen->error, $toevoegen->errno);
		}
		if ( $db->affected_rows === 1 ) {
			$aantal_toegevoegd++;
		}
		if ( $db->affected_rows === 2 ) {
			$aantal_bijgewerkt++;
		}
	}
}
$toevoegen->close();
printf("%d nummers geïmporteerd en %d bijgewerkt.\n", $aantal_toegevoegd, $aantal_bijgewerkt);
