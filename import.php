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

if ( is_dev() ) {
	error_reporting(E_ALL & ~E_NOTICE);
}

$link = Muzieklijsten_Database::getDB();

$excel = new PhpExcelReader;
$excel->read('powergold.xls');

$data = $excel->sheets[0]["cells"];

foreach ($data as $key => $arr) {
	if ( (strlen($arr[9]) == 9 || strlen($arr[9]) == 10) && preg_match('/\s/',$arr[9]) == false)  {
		
		$id = $arr[9];
		$year = $arr[8];
		$artist = $arr[2];
		$title = $arr[5];
		if ($year == "") $year = 'NULL';
		
		
		$sql = "SELECT muziek_id FROM muzieklijst_nummers WHERE muziek_id = '".$id."'";
		$result = mysqli_fetch_array($link->query($sql));
		$muziek_id = $result[0];
		
		$sql = "SELECT id FROM muzieklijst_nummers WHERE titel = '".addslashes($title)."' AND artiest = '".addslashes($artist)."'";
		$result = mysqli_fetch_array($link->query($sql));
		$db_id = $result[0];
		
		if ($muziek_id == "" && $db_id == "") {
			$sql = "INSERT INTO muzieklijst_nummers (muziek_id, titel, artiest, jaar) VALUES ('".$id."', '".addslashes($title)."', '".addslashes($artist)."', ".$year.")";
			$result = $link->query($sql);
			echo 'imported: '.$artist.' - '.$title.'<br>';
		} else {
			$sql = "UPDATE muzieklijst_nummers SET titel = '".addslashes($title)."', artiest = '".addslashes($artist)."', jaar = ".$year." WHERE muziek_id = '".$id."'";
			$result = $link->query($sql);
			echo 'updated: '.$artist.' - '.$title.'<br>';
		}
	}
}
