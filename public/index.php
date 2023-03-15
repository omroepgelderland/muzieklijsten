<?php

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

try {
	$lijst = Lijst::maak_uit_request(INPUT_GET);
} catch ( Muzieklijsten_Exception $e ) {
	throw new Muzieklijsten_Exception('Geef een lijst mee in de URL');
}

try {
	$body_classes = [];
	if ( $lijst->heeft_gebruik_recaptcha() ) {
		$body_classes[] = 'heeft-recaptcha';
	}
	if ( $lijst->is_actief() ) {
		$body_classes[] = 'is-actief';
	}
	if ( $lijst->is_max_stemmen_per_ip_bereikt() ) {
		$body_classes[] = 'max-ip-bereikt';
	}
	$body_classes_str = implode(' ', $body_classes);

	$velden_str =
		$lijst->get_veld_naam_html()
		.$lijst->get_veld_adres_html()
		.$lijst->get_veld_woonplaats_html()
		.$lijst->get_veld_telefoonnummer_html()
		.$lijst->get_veld_email_html()
		.$lijst->get_veld_uitzenddatum_html()
		.$lijst->get_veld_vrijekeus_html();
	foreach ( $lijst->get_extra_velden() as $extra_veld ) {
		$velden_str .= $extra_veld->get_formulier_html();
	}
	$recaptcha_sitekey = Config::get_instelling('recaptcha', 'sitekey');

	$metadata = [
		'lijst_id' => $lijst->get_id(),
		'minkeuzes' => $lijst->get_minkeuzes(),
		'maxkeuzes' => $lijst->get_maxkeuzes(),
		'artiest_eenmalig' => $lijst->is_artiest_eenmalig()
	];
	$metadata_str = htmlspecialchars(json_encode($metadata));

	$organisatie = htmlspecialchars(Config::get_instelling('organisatie'));
	$privacy_url = htmlspecialchars(Config::get_instelling('privacy_url'));
} catch ( \Throwable $e ) {
	Log::err($e);
	throw $e;
}

?>
<!DOCTYPE html>
<html lang="nl-NL">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title><?php echo $organisatie; ?> – <?php echo htmlspecialchars($lijst->get_naam()); ?></title>
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css">
		<!-- Scripts prod en dev -->
		<script src="js/runtime.js" defer></script>
		<script src="js/admin-fbshare-los_toevoegen-muzieklijst-resultaten.js" defer></script>
		<script src="js/admin-muzieklijst-resultaten.js" defer></script>
		<script src="js/muzieklijst-resultaten.js" defer></script>
		<script src="js/muzieklijst.js" defer></script>
		<!-- Styles prod -->
		<link rel="stylesheet" href="css/admin-fbshare-los_toevoegen-muzieklijst-resultaten.css">
		<link rel="stylesheet" href="css/muzieklijst.css">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-192p.png" sizes="192x192">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-16p.png" sizes="16x16">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-32p.png" sizes="32x32">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-96p.png" sizes="96x96">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-120p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-180p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-152p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-167p.png">
	</head>
	<body class="<?php echo $body_classes_str; ?>" data-metadata="<?php echo $metadata_str; ?>">
		<!-- FB share knop script -->
		<div id="fb-root"></div>
		<!-- Einde FB share knop script -->
		<form id="keuzeformulier" name="keuzeformulier" class="form-horizontal" role="form">
			<input type="hidden" name="lijst" value="<?php echo $lijst->get_id(); ?>">
			<div class="container-fluid">
				<div class="row">
					<div class="col-sm-12 is-niet-actief">
						<p>Er kan niet meer worden gestemd.</p>
					</div>
				</div>
				<div class="row is-actief">
					<div class="col-sm-12">
						<div id="table_placeholder"></div>
						<table id="nummers" class="display select" cellspacing="0" width="100%">
							<thead>
								<tr>
									<th></th>
									<th>Titel</th>
									<th>Artiest</th>
								</tr>
							</thead>
						</table>
					</div>
				</div>
			</form>
			<form id="stemmerformulier" name="stemmerformulier" method="POST" class="form-horizontal" role="form">
				<div class="row is-actief">
					<div class="col-sm-12" id="result"></div>
				</div>
				<div class="row is-actief">
					<div class="col-sm-12" id="contactform">
						<?php echo $velden_str; ?>
						<div class="form-group heeft-recaptcha">
							<label class="control-label col-sm-2" for="code"></label>
							<div class="col-sm-10">
								<div class="g-recaptcha" data-sitekey="<?php echo $recaptcha_sitekey; ?>"></div>
								<script src="https://www.google.com/recaptcha/api.js?hl=nl"></script>
							</div>
						</div>
						<div class="form-group">
							<div class="privacyinfo">
								<small>Je moet minimaal 16 jaar zijn om deel te nemen aan deze dienst. Je contactgegevens worden alleen gebruikt voor dit radioprogramma en worden maximaal drie maanden bewaard. Wil je meer informatie over hoe <?php echo $organisatie; ?> omgaat met je gegevens, lees dan ons <a href="<?php echo $privacy_url; ?>" target="_parent">privacystatement</a></small>	
							</div>
							<div class="form-group">
								<label class="control-label col-sm-2" for="submit"></label>
								<div class="col-sm-2">
									<button type="submit" class="btn btn-default" id="submit" name="submit">Versturen</button>
								</div>
							</div>
							<div class="form-group max-ip-bereikt">
								<label class="control-label col-sm-2" for="submit"></label>
								<div class="col-sm-10">
									<p>Het maximum aantal stemmen vanaf dit IP-adres is bereikt.</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>
	</body>
</html>
