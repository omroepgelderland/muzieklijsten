<?php

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

try {

	$stemmer = Stemmer::maak_uit_request(INPUT_GET);

	$query = <<<EOT
		SELECT n.artiest, n.titel
		FROM nummers n
		INNER JOIN stemmen sn ON
			sn.nummer_id = n.id
		INNER JOIN stemmers s ON
			s.id = {$stemmer->get_id()}
			AND s.id = sn.stemmer_id
		ORDER BY n.artiest, n.titel
	EOT;
	$res = DB::query($query);
	$nummers_meta = [];
	$nummers_html_str = '';
	$i = 1;
	foreach( $res as $r ) {
		$nummers_meta[] = sprintf(
			'%d) %s - %s',
			$i,
			htmlspecialchars($r['artiest']),
			htmlspecialchars($r['titel'])
		);
		$nummers_html_str .= sprintf(
			'<li>%s - %s</li>',
			htmlspecialchars($r['artiest']),
			htmlspecialchars($r['titel'])
		);
		$i++;
	}
	$nummers_meta_str = implode("\n", $nummers_meta);

	$root_url = Config::get_instelling('root_url');
	$og_url = "{$root_url}fbshare.php?stemmer={$stemmer->get_id()}";
	$og_image = "{$root_url}afbeeldingen/fbshare_top100.jpg";
	$jaar = (new \DateTime())->format('Y');
} catch ( \Throwable $e ) {
	Log::err($e);
	throw $e;
}

?>

<!DOCTYPE html>
<html lang="nl-NL" ng-app="fbShare" ng-controller="MainCtrl">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Delen op Facebook</title>
		<meta property="og:type" content="website">
		<meta property="fb:app_id" content="1269120393132176">
		<meta property="og:url" content="<?php echo $og_url; ?>">
		<meta property="og:locale" content="nl_NL">
		<meta property="og:image" content="<?php echo $og_image; ?>">
		<meta property="og:title" content="Dit is mijn keuze voor de Gelderse Top 100 <?php echo $jaar; ?>">
		<meta property="og:description" content="<?php echo $nummers_meta_str; ?>">
		<title>Mijn keuzes</title>
		<!-- Scripts dev -->
		<script type="text/javascript" src="js/vendors-node_modules_bootstrap_dist_js_npm_js-node_modules_bootstrap_dist_css_bootstrap_min_css.js" defer></script>
		<!-- Scripts prod -->
		<script type="text/javascript" src="js/runtime.js" defer></script>
		<script type="text/javascript" src="js/806.js" defer></script>
		<!-- Scripts prod en dev -->
		<script type="text/javascript" src="js/fbshare.js" defer></script>
		<!-- Styles prod -->
		<link rel="stylesheet" href="css/806.css">
		<link rel="stylesheet" href="css/fbshare.css">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-192p.png" sizes="192x192">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-16p.png" sizes="16x16">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-32p.png" sizes="32x32">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-96p.png" sizes="96x96">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-120p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-180p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-152p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-167p.png">
	</head>
	<body>
		<div class="container">
			<img src="afbeeldingen/fbshare_top100.jpg" class="img-responsive headerimage">
			<h2>Dit is mijn keuze voor de Gelderse Top 100 <?php echo $jaar; ?>:</h2>
			<ol>
				<?php echo $nummers_html_str; ?>
			</ol>
			<p>Ook meedoen? <a href="https://www.gld.nl/de-gelderse-top-100">Klik hier!</a></p>
		</div>
	</body>
</html>
