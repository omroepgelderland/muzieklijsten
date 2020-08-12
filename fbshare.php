<?php

require_once __DIR__.'/include/include.php';

$stemmer_id = (int)$_GET['stemmer'];

$db = Muzieklijsten_Database::getDB();
if ( $db === false ) {
	throw new SQLException('Database verbinding mislukt');
}
$sql = sprintf(
	'SELECT n.artiest, n.titel FROM muzieklijst_nummers n JOIN muzieklijst_stemmen sn ON sn.nummer_id = n.id JOIN muzieklijst_stemmers s ON s.id = %d AND s.id = sn.stemmer_id ORDER BY sn.id',
	$stemmer_id
);
$res = $db->query($sql);
if ( $res === false ) {
	throw new SQLException('Query mislukt');
}
$nummers_meta = '';
$nummers_html = '';
$i = 1;
foreach( $res as $r ) {
	$nummers_meta .= sprintf(
		'%d) %s - %s ',
		$i,
		$r['artiest'],
		$r['titel']
	);
	$nummers_html .= sprintf(
		'<li>%s - %s</li>',
		$r['artiest'],
		$r['titel']
	);
	$i++;
}

if ( is_dev() ) {
	$og_url = sprintf(
		'http://192.168.1.107/~%s/muzieklijsten/fbshare.php?stemmer=%d',
		get_developer(),
		$stemmer_id
	);
	$og_image = sprintf(
		'http://192.168.1.107/~%s/muzieklijsten/fbshare_top100.jpg',
		get_developer()
	);
} else {
	$og_url = 'https://web.omroepgelderland.nl/muzieklijsten/fbshare.php?stemmer='.$stemmer_id;
	$og_image = 'https://web.omroepgelderland.nl/muzieklijsten/fbshare_top100.jpg';
}

?>

<!DOCTYPE html>
<html lang="nl" ng-app="fbShare" ng-controller="MainCtrl">
	<head>
		<meta charset="utf-8">
		<meta property="og:type" content="website">
		<meta property="fb:app_id" content="1269120393132176">
		<meta property="og:url" content="<?php echo $og_url; ?>">
		<meta property="og:locale" content="nl_NL">
		<meta property="og:image" content="<?php echo $og_image; ?>">
		<meta property="og:title" content="Dit is mijn keuze voor de Gelderse Top 100 2017">
		<meta property="og:description" content="<?php echo $nummers_meta; ?>">
		<title>Mijn keuzes</title>
		<link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" rel="stylesheet">
		<link href="https://www.omroepgelderland.nl/Content/Styles/gelderland.min.css?v=1.23.1" rel="stylesheet">
		<script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.5.7/angular.js"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.5.7/angular-route.js"></script>
		<style>
			.headerimage {
				max-height: 25em;
				margin-bottom: 1em;
			}
		</style>
	</head>
	<body>
		<div class="container">
			<img src="fbshare_top100.jpg" class="img-responsive headerimage">
			<h2>Dit is mijn keuze voor de Gelderse Top 100 2017:</h2>
			<ol>
				<?php echo $nummers_html; ?>
			</ol>
			<p>Ook meedoen? <a href="https://www.omroepgelderland.nl/radio-degeldersetop100-2017">Klik hier!</a></p>
		</div>
		<script src="fbshare.js"></script>
	</body>
</html>
