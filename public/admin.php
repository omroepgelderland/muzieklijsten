<?php

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

try {

	login();

	$body_classes = ['admin'];
	try {
		$lijst = Lijst::maak_uit_request(INPUT_GET);
		$lijst_id = $lijst->get_id();
		$lijst_naam = htmlspecialchars($lijst->get_naam());
		$lijst_query = sprintf('?lijst=%d', $lijst->get_id());
		$body_classes[] = 'lijst-geselecteerd';
		$nummers = $lijst->get_nummers();
		$bewerk_knoppen_disabled = '';
		$bewerk_knoppen_title = '';
		$iframe_url = htmlspecialchars(sprintf(
			'%s?lijst=%d',
			Config::get_instelling('root_url'),
			$lijst_id
		));
		$iframe_code = htmlspecialchars("<iframe src=\"{$iframe_url}\" frameborder=\"0\" height=\"3000\" style=\"width: 100%; height: 3000px; border: none;\">");
	} catch ( Muzieklijsten_Exception $e ) {
		$lijst = null;
		$lijst_id = '';
		$lijst_naam = 'Nieuwe lijst';
		$lijst_query = '';
		$nummers = [];
		$bewerk_knoppen_disabled = ' disabled';
		$bewerk_knoppen_title = ' title="Kies eerst een lijst of maak een nieuwe lijst aan."';
		$iframe_url = '';
		$iframe_code = '';
	}

	$andere_lijsten = get_muzieklijsten();

	$select_lijst_html = '';
	foreach ( $andere_lijsten as $andere_lijst ) {
		if ( $andere_lijst->equals($lijst) ) {
			$selected = ' selected';
		} else {
			$selected = '';
		}
		$select_lijst_html .= sprintf(
			'<option value="%d"%s>%s</option>',
			$andere_lijst->get_id(),
			$selected,
			htmlspecialchars($andere_lijst->get_naam())
		);
	}

	$nummer_ids = [];
	foreach ( $nummers as $nummer ) {
		$nummer_ids[] = (string)$nummer->get_id();
	}
	$rows_selected = htmlspecialchars(json_encode($nummer_ids));

	$totaal_aantal_nummers = count(get_nummers());

	$organisatie = Config::get_instelling('organisatie');
	$nimbus_url = Config::get_instelling('nimbus_url');
	$body_classes_str = implode(' ', $body_classes);
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
		<title>Muzieklijsten beheer</title>
		<!-- Scripts prod en dev -->
		<script src="js/runtime.js" defer></script>
		<script src="js/admin-fbshare-los_toevoegen-muzieklijst.js" defer></script>
		<script src="js/admin-muzieklijst.js" defer></script>
		<script src="js/admin.js" defer></script>
		<!-- Styles prod -->
		<link rel="stylesheet" href="css/admin-fbshare-los_toevoegen-muzieklijst.css">
		<link rel="stylesheet" href="css/admin.css">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-192p.png" sizes="192x192">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-16p.png" sizes="16x16">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-32p.png" sizes="32x32">
		<link rel="icon" type="image/png" href="afbeeldingen/favicon-96p.png" sizes="96x96">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-120p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-180p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-152p.png">
		<link rel="apple-touch-icon" href="afbeeldingen/favicon-167p.png">
	</head>
	<body class="<?php echo $body_classes_str; ?>" data-lijst-id="<?php echo $lijst_id; ?>" data-lijst-naam="<?php echo $lijst_naam; ?>" data-rows-selected="<?php echo $rows_selected; ?>">

		<nav class="navbar navbar-default navbar-fixed-top">
			<div class="container-fluid">
				<table width="100%">
					<tr>
						<td><strong><?php echo $organisatie; ?> – Muzieklijsten</strong></td>
						<td>			
							<select id="lijstselect" name="lijst" class="form-control">
								<option value="">-- Selecteer een muzieklijst --</option>
								<?php echo $select_lijst_html; ?>
							</select>
						</td>
						<td align="right">
							<a href="beheer.php" data-toggle="modal" data-target="#nieuw" data-backdrop="static" data-keyboard="false" class="btn btn-primary" role="button">Nieuw</a>
						</td>
						<td align="right">
							<a href="beheer.php<?php echo $lijst_query; ?>" data-toggle="modal" data-target="#beheer" data-backdrop="static" data-keyboard="false" class="btn btn-primary<?php echo $bewerk_knoppen_disabled; ?>" role="button"<?php echo $bewerk_knoppen_title; ?>>Beheer</a>
						</td>
						<td align="right">
							<a id="resultaten" href="#" class="btn btn-primary<?php echo $bewerk_knoppen_disabled; ?>" role="button"<?php echo $bewerk_knoppen_title; ?>>Resultaten</a>
						</td>
						<!-- <td align="right">
							<button type="submit" form="beheer-nummers" class="btn btn-primary"<?php echo $bewerk_knoppen_disabled.$bewerk_knoppen_title; ?>>Opslaan</button>
						</td> -->
						<td align="right">
							<a href="#" data-toggle="modal" data-target="#modal-embed" data-backdrop="static" data-keyboard="false" class="btn btn-primary<?php echo $bewerk_knoppen_disabled; ?>" role="button"<?php echo $bewerk_knoppen_title; ?>>Embedden</a>
						</td>
					</tr>
				</table>
			</div>
		</nav>

		<form id="beheer-nummers" method="POST" class="lijst-geselecteerd">
			<div class="container-fluid beschikbaar">
				<div class="row">
					<div class="col-sm-6">
						<h4>Beschikbaar (<?php echo $totaal_aantal_nummers; ?>)</h4>
						<hr>
						<table id="beschikbare-nummers" class="display select" cellspacing="0" width="100%">
							<thead>
								<tr>
									<th></th>
									<th>Titel</th>
									<th>Artiest</th>
									<th>Jaar</th>
								</tr>
							</thead>
						</table>
					</div>
					<div class="col-sm-6">
						<div id="result"></div>
					</div>
				</div>
			</div>
		</form>

		<div class="modal fade lijstinstellingen" id="beheer" role="dialog">
			<div class="modal-dialog">
				<div class="modal-header modal-top">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
					<h4 class="modal-title">Beheer van de lijst "<?php echo $lijst_naam; ?>"</h4>
				</div>
				<div class="modal-content"></div>
				<div class="modal-footer modal-end">
					<button type="button" class="btn btn-default" data-dismiss="modal">Sluiten</button>
				</div>
			</div>
		</div>

		<div class="modal fade lijstinstellingen" id="nieuw" role="dialog">
			<div class="modal-dialog">
				<div class="modal-header modal-top">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
					<h4 class="modal-title">Nieuwe muzieklijst</h4>
				</div>
				<div class="modal-content"></div>
				<div class="modal-footer modal-end">
					<button type="button" class="btn btn-default" data-dismiss="modal">Sluiten</button>
				</div>
			</div>
		</div>

		<div class="modal fade" id="modal-embed" role="dialog">
			<div class="modal-dialog">
				<div class="modal-header modal-top">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
					<h4 class="modal-title">Embed de lijst "<?php echo $lijst_naam; ?>"</h4>
				</div>
				<div class="modal-content">
					<div class="container-fluid">
						<h5>In <a href="<?php echo $nimbus_url; ;?>" target="_blank">Nimbus</a></h5>
						<p>Voeg een iframe toe en vul in:</p>
						<input type="text" value="<?php echo $iframe_url; ?>" readonly>
						<h5>In het <a href="https://cms.regiogroei.cloud/" target="_blank">Regiogroei CMS</a></h5>
						<p>Voeg een iframe-blok toe.<br>
						Hoogte op mobiele devices: 3300<br>
						Hoogte op tablet: 2900<br>
						Hoogte op desktop: 2900<br>
						Link:</p>
						<input type="text" value="<?php echo $iframe_url; ?>" readonly>
						<h5>Op een externe website</h5>
						<input type="text" value="<?php echo $iframe_code; ?>" readonly>
					</div>
				</div>
				<div class="modal-footer modal-end">
					<button type="button" class="btn btn-default" data-dismiss="modal">Sluiten</button>
				</div>
			</div>
		</div>

	</body>
</html>
