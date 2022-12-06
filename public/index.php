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
	if ( $lijst->heeft_veld_telefoonnummer() ) {
		$body_classes[] = 'veld-telefoonnummer';
	}
	if ( $lijst->heeft_veld_email() ) {
		$body_classes[] = 'veld-email';
	}
	if ( $lijst->heeft_veld_woonplaats() || $lijst->heeft_veld_adres() ) {
		$body_classes[] = 'veld-woonplaats';
	}
	if ( $lijst->heeft_veld_adres() ) {
		$body_classes[] = 'veld-adres';
	}
	if ( $lijst->heeft_veld_uitzenddatum() ) {
		$body_classes[] = 'veld-uitzenddatum';
	}
	if ( $lijst->heeft_veld_vrijekeus() ) {
		$body_classes[] = 'veld-vrijekeus';
	}
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

	$extra_velden_html = '';
	foreach ( $lijst->get_extra_velden() as $extra_veld ) {
		$extra_velden_html .= $extra_veld->get_formulier_html();
	}
	$recaptcha_sitekey = Config::get_instelling('recaptcha', 'sitekey');

	$artiest_eenmalig = $lijst->is_artiest_eenmalig()
		? 'true'
		: 'false';

	$organisatie = Config::get_instelling('organisatie');
	$privacy_url = Config::get_instelling('privacy_url');
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
		<title><?php echo $organisatie; ?> – <?php echo $lijst->get_naam(); ?></title>
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css">
		<!-- Scripts dev -->
		<script type="text/javascript" src="js/vendors-node_modules_bootstrap_dist_js_npm_js-node_modules_bootstrap_dist_css_bootstrap_min_css.js" defer></script>
		<script type="text/javascript" src="js/vendors-node_modules_datatables_net-dt_js_dataTables_dataTables_js.js" defer></script>
		<script type="text/javascript" src="js/vendors-node_modules_eonasdan-bootstrap-datetimepicker_src_js_bootstrap-datetimepicker_js-nod-84fd46.js" defer></script>
		<!-- Scripts prod -->
		<script type="text/javascript" src="js/runtime.js" defer></script>
		<script type="text/javascript" src="js/806.js" defer></script>
		<script type="text/javascript" src="js/290.js" defer></script>
		<script type="text/javascript" src="js/498.js" defer></script>
		<!-- Scripts prod en dev -->
		<script type="text/javascript" src="js/muzieklijst.js" defer></script>
		<!-- Styles prod -->
		<link rel="stylesheet" href="css/806.css">
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
	<body class="<?php echo $body_classes_str; ?>" data-minkeuzes="<?php echo $lijst->get_minkeuzes(); ?>" data-maxkeuzes="<?php echo $lijst->get_maxkeuzes(); ?>" data-artiest-eenmalig="<?php echo $artiest_eenmalig; ?>">
		<!-- FB share knop script -->
		<div id="fb-root"></div>
		<!-- Einde FB share knop script -->
		<form id="stemformulier" name="form" method="POST" class="form-horizontal" role="form">
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
				<div class="row is-actief">
					<div class="col-sm-12" id="result"></div>
				</div>
				<div class="row is-actief">
					<div class="col-sm-12" id="contactform">
						<div class="form-group">
							<label class="control-label col-sm-2" for="naam">Naam</label>
							<div class="col-sm-10">
								<input type="text" class="form-control" id="naam" name="naam">
							</div>
						</div>
						<div class="form-group veld-adres">
							<label class="control-label col-sm-2" for="adres">Adres</label>
							<div class="col-sm-10">
								<input type="text" class="form-control" id="adres" name="adres">
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-sm-2" for="postcode">Postcode</label>
							<div class="col-sm-10">
								<input type="text" class="form-control" id="postcode" name="postcode">
							</div>
						</div>
						<div class="form-group veld-woonplaats">
							<label class="control-label col-sm-2" for="woonplaats">Woonplaats</label>
							<div class="col-sm-10">
								<input type="text" class="form-control" id="woonplaats" name="woonplaats">
							</div>
						</div>
						<div class="form-group veld-telefoonnummer">
							<label class="control-label col-sm-2" for="telefoonnummer">Telefoonnummer</label>
							<div class="col-sm-10">
								<input type="text" class="form-control" id="telefoonnummer" name="telefoonnummer">
							</div>
						</div>	
						<div class="form-group veld-email">
							<label class="control-label col-sm-2" for="veld_email">E-mailadres</label>
							<div class="col-sm-10">
								<input type="text" class="form-control" id="veld_email" name="veld_email">
							</div>
						</div>
						<div class="form-group veld-uitzenddatum">
							<label class="control-label col-sm-2" for="veld_uitzenddatum">Uitzenddatum</label>
							<div class="col-sm-10">
								<div class="input-group date" id="datetimepicker">
									<input type="text" name="veld_uitzenddatum" id="veld_uitzenddatum" class="form-control" placeholder="selecteer een datum">
									<span class="input-group-addon">
										<span class="glyphicon glyphicon-calendar"></span>
									</span>
								</div>
							</div>
						</div>
						<div class="form-group veld-vrijekeus">
							<label class="control-label col-sm-2" for="veld_vrijekeus">Vrije keuze</label>
							<div class="col-sm-10">
								<input type="text" class="form-control" id="veld_vrijekeus" name="veld_vrijekeus" placeholder="Vul hier je eigen favoriet in">
							</div>
						</div>
						<?php echo $extra_velden_html; ?>

						<div class="form-group heeft-recaptcha">
							<label class="control-label col-sm-2" for="code"></label>
							<div class="col-sm-10">
								<div class="g-recaptcha" data-sitekey="<?php echo $recaptcha_sitekey; ?>"></div>
								<script type="text/javascript" src="https://www.google.com/recaptcha/api.js?hl=nl"></script>
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
