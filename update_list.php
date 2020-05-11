<?php

require_once __DIR__.'/include/include.php';

if ( is_dev() ) {
	error_reporting(E_ALL & ~E_NOTICE);
}

$link = Muzieklijsten_Database::getDB();

$lijst = (int)$_POST['lijst'];

login();

$sql = "DELETE FROM muzieklijst_nummers_lijst WHERE lijst_id = ".$lijst;
$result = $link->query($sql);

foreach ($_POST["id"] as $key => $value) {
	$sql = sprintf(
		'INSERT INTO muzieklijst_nummers_lijst (nummer_id, lijst_id) VALUES (%d, %d)',
		$value,
		$lijst
	);
	$result = $link->query($sql);
	
	
}
