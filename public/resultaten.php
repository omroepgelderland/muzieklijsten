<?php

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

/**
 * Geeft aan of de gebruiker adminrechten heeft.
 * @return bool
 */
function is_admin() {
	return filter_input(INPUT_GET, 'admin', FILTER_VALIDATE_BOOL);
}

/**
 * Genereert de HTML voor de resultaten van één nummer in de lijst.
 * Deze is uit te klappen zodat de individuele stemmen sichtbaar zijn.
 * @param Lijst $muzieklijst De stemlijst
 * @param Nummmer $nummer Het nummer in de uitslag.
 */
function get_html_nummer(
	Lijst $lijst,
	Nummer $nummer,
	?\DateTime $van = null,
	?\DateTime $tot = null
): string {
	$stemmen = $lijst->get_stemmen($nummer, $van, $tot);
	$tr_classes = [
		'nummer'
	];
	if ( count($stemmen) > 0 ) {
		$tr_classes = array_merge($tr_classes, [
			'heeft-stemmen',
			'accordion-toggle',
			'hand',
			'collapsed'
		]);
	}
	if ( count($stemmen) > 0 && $stemmen[0]->is_behandeld() ) {
		$tr_classes[] = 'success';
	}
	$uitklaptabel_id = "uitklap-tabel-{$nummer->get_id()}";
	$titel = htmlspecialchars($nummer->get_titel());
	$artiest = htmlspecialchars($nummer->get_artiest());
	$aantal_stemmen = count($stemmen);

	// Variabele velden
	$extra_velden = '';
	foreach ( $lijst->get_extra_velden() as $extra_veld ) {
		$extra_velden .= sprintf(
			'<th class="col-sm-2">%s</th>',
			htmlspecialchars($extra_veld->get_label())
		);
	}

	// inhoud van de uitklapbare tabel per nummer, met daarin de lijst met stemmers.
	$stemmers_html = '';
	foreach ( $stemmen as $stem ) {
		$stemmers_html .= get_html_stemmer($stem);
	}

	$tr_classes_str = implode(' ', $tr_classes);

	if ( count($stemmen) > 0 ) {
		$collapse_html = <<<EOT
			<div class="accordion-body collapse" id="{$uitklaptabel_id}">
				<div class="bs-callout bs-callout-info" style="margin:0px;">
					<table class="table">
						<thead>
							<tr>
								<th class="col-sm-2">Naam</th>
								<th class="col-sm-2 veld-woonplaats">Woonplaats</th>
								<th class="col-sm-2 veld-adres">Adres</th>
								<th class="col-sm-2 veld-telefoonnummer">Telefoonnummer</th>
								<th class="col-sm-2 veld-email">E-mailadres</th>
								<th class="col-sm-2 veld-uitzenddatum">Uitzenddatum</th>
								<th class="col-sm-2 veld-vrijekeus">Vrije keuze</th>
								{$extra_velden}
								<th class="col-sm-2">Aangevraagd op</th>
								<th class="col-sm-2">Toelichting</th>
								<th class="col-sm-1 text-center" align="center">Behandeld</th>
								<th class="col-sm-1"></th>
							</tr>
						</thead>
						<tbody>{$stemmers_html}</tbody>
					</table>
				</div>
			</div>
		EOT;
	} else {
		$collapse_html = '';
	}

	return <<<EOT
		<tr data-toggle="collapse" data-target="#{$uitklaptabel_id}" class="{$tr_classes_str}" data-nummer-id="{$nummer->get_id()}">
			<td class="text-center"><i class="fa fa-plus-square heeft-stemmen"></i></td>
			<td>{$titel}</td>
			<td>{$artiest}</td>
			<td class="text-center aantal-stemmen">{$aantal_stemmen}</td>
			<td class="admin">
				<div title="Nummer verwijderen">
					<i class="verwijder-nummer fa fa-times fa-1 hand text-danger"></i>
				</div>
			</td>
		</tr>
		<tr>
			<td colspan="4" class="hiddenRow">{$collapse_html}</td>
		</tr>
	EOT;
}

/**
 * Geef een rij met informatie over één stemmer op één nummer.
 * @param Stem $stem
 * @return string
 */
function get_html_stemmer( Stem $stem ): string {
	// $stem_id = "{$stem->nummer->get_id()}-{$stem->lijst->get_id()}-{$stem->stemmer->get_id()}";
	if ( $stem->is_behandeld() ) {
		$behandeld_success = ' success';
		$behandeld_checked = ' checked';
	} else {
		$behandeld_success = '';
		$behandeld_checked = '';
	}
	$variabele_velden = get_stemmer_html_get_td_velden($stem);
	$tijd_str = $stem->stemmer->get_tijdstip()->format('d-m-Y H:i:s');
	$toelichting = htmlspecialchars($stem->get_toelichting());
	return <<<EOT
		<tr class="stemmer{$behandeld_success}" data-nummer-id="{$stem->nummer->get_id()}" data-stemmer-id="{$stem->stemmer->get_id()}">
			{$variabele_velden}
			<td>{$tijd_str}</td>
			<td>{$toelichting}</td>
			<td class="text-center hand">
				<input type="checkbox" class="hand"{$behandeld_checked}>
			</td>
			<td>
				<div class="hand stem-verwijderen" title="Stem verwijderen">
					<i class="fa fa-times fa-1 text-danger"></i>
				</div>
			</td>
		</tr>
	EOT;
}

/**
 * Geeft de waarden van de variabele velden voor een stemmer.
 * @param Stem $stem
 * @return string
 */
function get_stemmer_html_get_td_velden( Stem $stem ): string {
	$stemmer = $stem->stemmer;
	$lijst = $stem->lijst;

	$stemmer_naam = htmlspecialchars($stemmer->get_naam());
	$woonplaats = htmlspecialchars((string)$stemmer->get_woonplaats());
	$adres = htmlspecialchars((string)$stemmer->get_adres());
	$postcode = htmlspecialchars((string)$stemmer->get_postcode());
	$telefoonnummer = htmlspecialchars((string)$stemmer->get_telefoonnummer());
	$emailadres = htmlspecialchars((string)$stemmer->get_emailadres());
	$uitzenddatum = htmlspecialchars((string)$stemmer->get_uitzenddatum());
	$vrijekeus = htmlspecialchars((string)$stemmer->get_vrijekeus());

	$html = <<<EOT
		<td title="{$stemmer->get_ip()}">{$stemmer_naam}</td>
		<td class="veld-woonplaats">{$woonplaats}</td>
		<td class="veld-adres">{$adres}<br>{$postcode}</td>
		<td class="veld-telefoonnummer"><a href="tel:{$telefoonnummer}">{$telefoonnummer}</a></td>
		<td class="veld-email"><a href="mailto:{$emailadres}">{$emailadres}</a></td>
		<td class="veld-uitzenddatum">{$uitzenddatum}</td>
		<td class="veld-vrijekeus">{$vrijekeus}</td>
	EOT;
	
	// Variabele velden
	foreach ( $lijst->get_extra_velden() as $extra_veld ) {
		try {
			$waarde = htmlspecialchars($extra_veld->get_stemmer_waarde($stemmer));
		} catch ( Muzieklijsten_Exception $e ) {
			$waarde = '';
		}
		$html .= "<td>{$waarde}</td>";
	}
	
	return $html;
}

try {

	login();

	$lijst = Lijst::maak_uit_request(INPUT_GET);

	$van = filter_input_van_tot('van');
	$tot = filter_input_van_tot('tot');

	$naam = $lijst->get_naam();

	$body_classes = [];
	if ( is_admin() ) {
		$body_classes[] = 'admin';
	}
	if ( $lijst->heeft_veld_telefoonnummer() ) {
		$body_classes[] = 'veld-telefoonnummer';
	}
	if ( $lijst->heeft_veld_email() ) {
		$body_classes[] = 'veld-email';
	}
	if ( $lijst->heeft_veld_woonplaats() ) {
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

	$body_classes_str = implode(' ', $body_classes);

	$stemmen = $lijst->get_stemmen(null, $van, $tot);
	$stemmers = $lijst->get_stemmers($van, $tot);

	$stemmen_tabel = '';

	$nummers = $lijst->get_nummers_volgorde_aantal_stemmen($van, $tot);
	foreach ( $nummers as $nummer ) {
		$stemmen_tabel .= get_html_nummer($lijst, $nummer, $van, $tot);
	}

	$van_str = isset($van)
		? $van->format('d-m-Y')
		: '';
	$tot_str = isset($tot)
		? $tot->format('d-m-Y')
		: '';
} catch ( \Throwable $e ) {
	Log::err($e);
	throw $e;
}

?>
<!DOCTYPE HTML>
<html lang="nl-NL">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title><?php echo $naam; ?> – Resultaten</title>	
		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css">
		<!-- Scripts dev -->
		<script type="text/javascript" src="js/vendors-node_modules_bootstrap_dist_js_npm_js-node_modules_bootstrap_dist_css_bootstrap_min_css.js" defer></script>
		<script type="text/javascript" src="js/vendors-node_modules_datatables_net-dt_js_dataTables_dataTables_js.js" defer></script>
		<script type="text/javascript" src="js/vendors-node_modules_eonasdan-bootstrap-datetimepicker_src_js_bootstrap-datetimepicker_js-nod-84fd46.js" defer></script>
		<script type="text/javascript" src="js/vendors-node_modules_eonasdan-bootstrap-datetimepicker_build_css_bootstrap-datetimepicker_min-051fae.js" defer></script>
		<!-- Scripts prod -->
		<script type="text/javascript" src="js/runtime.js" defer></script>
		<script type="text/javascript" src="js/806.js" defer></script>
		<script type="text/javascript" src="js/290.js" defer></script>
		<script type="text/javascript" src="js/498.js" defer></script>
		<!-- Scripts prod en dev -->
		<script type="text/javascript" src="js/resultaten.js" defer></script>
		<!-- Styles prod -->
		<link rel="stylesheet" href="css/806.css">
		<link rel="stylesheet" href="css/resultaten.css">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-192p.png" sizes="192x192">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-16p.png" sizes="16x16">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-32p.png" sizes="32x32">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-96p.png" sizes="96x96">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-120p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-180p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-152p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-167p.png">
	</head>
	<body class="<?php echo $body_classes_str; ?>" data-lijst-id="<?php echo $lijst->get_id(); ?>">
		<form method="post">
			<div id="page-wrapper">
				<div class="row">
					<div class="col-sm-12">
						<div class="col-sm-5"></div>
						<div class="col-sm-2">
							<div class="input-group">
								<input type="text" id="resultaatfilter" name="resultaatfilter" class="form-control" placeholder="Filter">
							</div>
						</div>
						<div class="col-sm-2">
							<div class="input-group date" id="datetimepicker1">
								<input type="text" name="van" class="form-control" placeholder="Datum van" value="<?php echo $van_str; ?>">
								<span class="input-group-addon">
									<span class="glyphicon glyphicon-calendar"></span>
								</span>
							</div>
						</div>
						<div class="col-sm-2">
							<div class="input-group date" id="datetimepicker2">
								<input type="text" name="tot" class="form-control" placeholder="Datum tot" value="<?php echo $tot_str; ?>">
								<span class="input-group-addon">
									<span class="glyphicon glyphicon-calendar"></span>
								</span>
							</div>
						</div>
						<div class="col-sm-1">
							<input type="submit" value="OK" class="btn btn-default">
						</div>
					</div>
					<div class="col-sm-12">
						<div class="table-responsive">
							<table id="resultaten" class="table table-striped">
								<thead>                                        
									<tr>
										<th></th>
										<th>Titel</th>
										<th>Artiest</th>
										<th class="text-center"><span id="totaal-aantal-stemmen"><?php echo count($stemmen); ?></span> stemmen<br>
										<span id="totaal-aantal-stemmers"><?php echo count($stemmers); ?></span> stemmers</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
									<?php echo $stemmen_tabel; ?>                                
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</form>
	</body>
</html>
