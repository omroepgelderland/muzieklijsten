<?php
$link = mysqli_connect("localhost","w3omrpg","H@l*lOah","rtvgelderland") or die("Error " . mysqli_error($link));

$lijst = (int)$_POST['lijst'];

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Inloggen"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Je moet inloggen om deze pagina te kunnen zien.';
    exit;
} else {
	if (($_SERVER['PHP_AUTH_USER'] == "gld") AND ($_SERVER["PHP_AUTH_PW"] = "muziek=fijn")) {
	

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



}
}
?>
