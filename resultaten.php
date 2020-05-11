<?php

require_once __DIR__.'/include/include.php';

//function formatDate($date) {
//	if ($date) {
//		$date_formatted = DateTime::createFromFormat('Y-m-d H:i:s', $date);
//		return $date_formatted->format('d-m-Y H:i:s');
//	}
//}

function formatDate2($date) {
	if ($date) {
		$date_formatted = DateTime::createFromFormat('d-m-Y', $date);
		return $date_formatted->format('Y-m-d');
	}
}

/**
 * Geeft aan of de gebruiker adminbrechten heeft.
 * @return boolean
 */
function is_admin() {
	return $_GET["admin"] == 1;
}

/**
 * Geneereert de HTML voor de resultaten van één nummer in de lijst.
 * Deze is uit te klappen zodat de individuele stemmen sichtbaar zijn.
 * @param Muzieklijst $muzieklijst De stemlijst
 * @param Nummmer $nummer Het nummer in de uitslag.
 */
function get_stemresultaat_html_rij( $muzieklijst, $nummer ) {
	$stemmen = $muzieklijst->get_stemmen($nummer);
	$var3 = '';
	if ( count($stemmen) > 0 ) {
		if ( $stemmen[0]->is_behandeld() ) {
			$var3 = ' success';
		}
	}
	
	if ( is_admin() ) {
		$verwijderen_cel = sprintf(
			'<td><div title="Nummer verwijderen"><i data-id="%d" class="delSong fa fa-times fa-1 hand text-danger"></i></div></td>',
			$nummer->get_id()
		);
	} else {
		$verwijderen_cel = '<td></td>';
	}
	
	$nummer_id = $nummer->get_id();
	$uitklaptabel_id = 'uitklap-tabel-'.$nummer->get_id();
	$titel = $nummer->get_titel();
	$artiest = $nummer->get_artiest();
	$aantal_stemmen = count($stemmen);
	$th_velden = get_stemresultaat_html_rij_get_th_velden($muzieklijst);
	$stemmers_html = get_stemmers_per_nummer_html($muzieklijst, $nummer);
	return "<tr id=\"song_{$nummer_id}\" data-toggle=\"collapse\" data-target=\"#{$uitklaptabel_id}\" class=\"accordion-toggle hand {$var3}\">
					<td style=\"text-align:center\"><i class=\"fa fa-minus-square\"></i></td>
					<td>{$titel}</td>
					<td>{$artiest}</td>
					<td class=\"text-center\">{$aantal_stemmen}</td>
					{$verwijderen_cel}
				</tr>
				<tr>
					<td colspan=\"4\" class=\"hiddenRow\">
						<div class=\"accordion-body collapse in\" id=\"{$uitklaptabel_id}\" style=\"\">
							<div class=\"bs-callout bs-callout-info\" style=\"margin:0px;\">
								<table class=\"table\">
								<thead>
									<tr>
										<th class=\"col-sm-2\">Naam</th>
										{$th_velden}
										<th class=\"col-sm-2\">Aangevraagd op</th>
										<th class=\"col-sm-2\">Toelichting</th>
										<th class=\"col-sm-1 text-center\" align=\"center\">Behandeld</th>
										<th class=\"col-sm-1\"></th>
									</tr>
								</thead>
								<tbody>
								{$stemmers_html}
								</tbody></table>
							</div>
						</div>
					</td>
				</tr>";
}

/**
 * Geeft de variabale kolomtitels in de resultatenlijst.
 * Dit is afhankelijk van welke velden geactiveerd zijn in de muzieklijst.
 * @param Muzieklijst $muzieklijst
 * @return string
 */
function get_stemresultaat_html_rij_get_th_velden( $muzieklijst ) {
	$html = '';
	
	// Vaste variabele velden
	if ( $muzieklijst->heeft_veld_woonplaats() || $muzieklijst->heeft_veld_adres() ) {
		$html .= '<th class="col-sm-2">Woonplaats</th>';
	}
	if ( $muzieklijst->heeft_veld_adres() ) {
		$html = '<th class="col-sm-2">Adres</th>';
	}
	if ( $muzieklijst->heeft_veld_telefoonnummer() ) {
		$html = '<th class="col-sm-2">Telefoonnummer</th>';
	}
	if ( $muzieklijst->heeft_veld_email() ) {
		$html .= '<th class="col-sm-2">E-mailadres</th>';
	}
	if ( $muzieklijst->heeft_veld_uitzenddatum() ) {
		$html .= '<th class="col-sm-2">Uitzenddatum</th>';
	}
	if ( $muzieklijst->heeft_veld_vrijekeus() ) {
		$html .= '<th class="col-sm-2">Vrije keuze</th>';
	}
	
	// Variabale variabele velden
	foreach ( $muzieklijst->get_extra_velden() as $extra_veld ) {
		$html .= sprintf(
			'<th class="col-sm-2">%s</th>',
			$extra_veld->get_label()
		);
	}
	return $html;
}

/**
 * Geeft de inhoud van de uitklapbare tabel per nummer, met daarin de lijst met stemmers.
 * @param Muzieklijst $muzieklijst
 * @param Nummer $nummer
 * @return string
 */
function get_stemmers_per_nummer_html( $muzieklijst, $nummer ) {
	$html = '';
	foreach ( $muzieklijst->get_stemmen($nummer) as $stem ) {
		$html .= get_stemmer_html($stem);
	}
	return $html;
}

/**
 * Geef een rij met informatie over een stemmer op een nummer.
 * @param Stem $stem
 * @return string
 */
function get_stemmer_html( $stem ) {
	$stem_id = $stem->get_id();
	if ( $stem->is_behandeld() ) {
		$behandeld_success = 'success';
		$behandeld_checked = 'checked';
	} else {
		$behandeld_success = '';
		$behandeld_checked = '';
	}
	$variabele_velden = get_stemmer_html_get_td_velden($stem);
	$tijd_str = $stem->get_stemmer()->get_tijdstip()->format('d-m-Y H:i:s');
	$toelichting = $stem->get_toelichting();
	return "<tr id=\"row_{$stem_id}\" class=\"{$behandeld_success}\">
									{$variabele_velden}
									<td>{$tijd_str}</td>
									<td>{$toelichting}</td>
									<td class=\"text-center hand\" onclick=\"$('#{$stem_id}').trigger('click');\"><input type=\"checkbox\" class=\"hand\" id=\"{$stem_id}\" {$behandeld_checked} onclick=\"$('#{$stem_id}').trigger('click');\"></td>
									<td><div title=\"Resultaat verwijderen\"><i class=\"fa fa-times fa-1 hand text-danger\" onclick=\"if (confirm('Dit resultaat verwijderen?')) { delResult('{$stem_id}'); }\"></i></div></td>
								</tr>";
}

/**
 * Geeft de waarden van de variabele velden voor een stemmer.
 * @param Stem $stem
 * @return string
 */
function get_stemmer_html_get_td_velden( $stem ) {
	$stemmer = $stem->get_stemmer();
	$lijst = $stem->get_lijst();
	$var1 = '192.168.103.107';
	$var2 = 'naam';
	$html = sprintf(
		'<td title="%s">%s</td>',
		$stemmer->get_ip(),
		$stemmer->get_naam()
	);
	
	// Vaste variabele velden
	if ( $lijst->heeft_veld_woonplaats() || $lijst->heeft_veld_adres() ) {
		$html .= sprintf('<td>%s</td>', $stemmer->get_woonplaats());
	}
	if ( $lijst->heeft_veld_adres() ) {
		$html .= sprintf(
			'<td>%s<br>%s</td>',
			$stemmer->get_adres(),
			$stemmer->get_postcode()
		);
	}
	if ( $lijst->heeft_veld_telefoonnummer() ) {
		$html .= sprintf('<td>%s</td>', $stemmer->get_telefoonnummer());
	}
	if ( $lijst->heeft_veld_email() ) {
		$html .= sprintf('<td>%s</td>', $stemmer->get_emailadres());
	}
	if ( $lijst->heeft_veld_uitzenddatum() ) {
		$html .= sprintf('<td>%s</td>', $stemmer->get_uitzenddatum());
	}
	if ( $lijst->heeft_veld_vrijekeus() ) {
		$html .= sprintf('<td>%s</td>', $stemmer->get_vrijekeus());
	}
	
	// Variabele variabele velden
	foreach ( $lijst->get_extra_velden() as $extra_veld ) {
		try {
			$waarde = $extra_veld->get_stemmer_waarde($stemmer);
		} catch ( Muzieklijsten_Exception $e ) {
			$waarde = '';
		}
		$html .= sprintf('<td>%s</td>', $waarde);
	}
	
	return $html;
}

if ( is_dev() ) {
	error_reporting(E_ALL & ~E_NOTICE);
}

login();

$link = Muzieklijsten_Database::getDB();
$result = $link->query("SET NAMES 'utf8'");

$id = (int)$_POST['id'];
$lijst = (int)$_POST['lijst'];

if ($_POST["behandeld"] == "1") {
	$sql = "UPDATE muzieklijst_stemmen SET behandeld = 1 WHERE id = ".$id;
	$result = $link->query($sql);
	exit();
}
if ($_POST["behandeld"] == "0") {
	$sql = "UPDATE muzieklijst_stemmen SET behandeld = 0 WHERE id = ".$id;
	$result = $link->query($sql);
	exit();
}

if ($_POST["delresult"] == "1") {
	$sql = "SELECT stemmer_id FROM muzieklijst_stemmen WHERE id = ".$id;
	$result = mysqli_fetch_array($link->query($sql));
	$stemmer_id = $result[0];
	
	$sql = "DELETE FROM muzieklijst_stemmen WHERE id = ".$id;
	$result = $link->query($sql);
	
	$sql = "DELETE FROM muzieklijst_stemmers WHERE id = ".$stemmer_id;
	$result = $link->query($sql);
	
	exit();
}

if ($_POST["delsong"] == "1") {
	
	$sql = "DELETE FROM muzieklijst_nummers_lijst WHERE nummer_id = ".$id." AND lijst_id = ".$lijst;
	$result = $link->query($sql);
	$sql = "DELETE FROM muzieklijst_stemmen WHERE nummer_id = ".$id." AND lijst_id = ".$lijst;
	$result = $link->query($sql);
	$sql = "SELECT stemmer_id FROM muzieklijst_stemmen WHERE nummer_id = ".$id." AND lijst_id = ".$lijst;
	$result = $link->query($sql);
	while ($r = mysqli_fetch_array($result)) {
		$sql = "DELETE FROM muzieklijst_stemmers WHERE id = ".$r["stemmer_id"];
		
	}
	exit();
}

$lijst = (int)$_GET['lijst'];
$muzieklijst = new Muzieklijst($lijst);

$sql = "SELECT naam FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$naam = $result[0];

$sql = "SELECT veld_email FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_email = $result[0];

$sql = "SELECT veld_woonplaats FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_woonplaats = $result[0];

$sql = "SELECT veld_adres FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_adres = $result[0];

$sql = "SELECT veld_uitzenddatum FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_uitzenddatum = $result[0];

$sql = "SELECT veld_vrijekeus FROM muzieklijst_lijsten WHERE id = ".$lijst;
$result = mysqli_fetch_array($link->query($sql));
$veld_vrijekeus = $result[0];

?>
<!DOCTYPE HTML>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/r/dt/jq-2.1.4,dt-1.10.8/datatables.min.css">
	<link rel="stylesheet" type="text/css" href="css/bootstrap-datetimepicker.min.css">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
	<title>Resultaten - <?php echo $naam; ?></title>
	
	<script src="js/jquery-1.11.2.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script src="js/moment.js"></script>
	<script src="js/nl.js"></script>
	<script src="js/bootstrap-datetimepicker.js"></script>

	<style type="text/css">
	body {
		margin-top: 20px;
		margin-left: 20px;
		margin-right: 20px;
		<?php if ($_GET["admin"] == 1) { ?>
		overflow: hidden;
		<?php } ?>
	}
	.hiddenRow {
		padding: 0 !important;
	}

	.hand {
		cursor: pointer;
	}
	
	.behandeld {
		background: #ee7f01;
	}

/* Base styles (regardless of theme) */
.bs-callout {
margin: 20px 0;
padding: 20px 20px 1px 20px;
border-left: 5px solid #eee;
}


.bs-callout p:last-child {
margin-bottom: 0;
}

.bs-callout code,
.bs-callout .highlight {
background-color: #fff;
}


.bs-callout-info {
background-color: #f0f7fd;
border-color: #d0e3f0;
}

        </style>


</head>
<body>
<form method="post">
<div id="page-wrapper">
	<div class="row">
	
		<div class="col-sm-12">
			<div class="col-sm-7"><?php if ($_GET["admin"] != 1) echo '<h4>Resultaten van de lijst "'.$naam.'"</h4>'; ?></div>
			<div class="col-sm-2">
				<div class='input-group date' id='datetimepicker1'>
					<input type='text' name="van" class="form-control" placeholder="Datum van" value="<?php echo $_POST["van"]; ?>">
					<span class="input-group-addon">
						<span class="glyphicon glyphicon-calendar"></span>
					</span>
				</div>
			</div>
			
			<div class="col-sm-2">
				<div class='input-group date' id='datetimepicker2'>
					<input type='text' name="tot" class="form-control" placeholder="Datum tot" value="<?php echo $_POST["tot"]; ?>">
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
				
				<table class="table table-striped">
				<thead>                                        
					<tr>
						<th></th>
						<th>Titel</th>
						<th>Artiest</th>
						<th class="text-center">Aantal stemmen
<?php
$sql = "SELECT COUNT(*) as aantal FROM muzieklijst_stemmen s, muzieklijst_stemmers t WHERE s.stemmer_id = t.id AND lijst_id = ".$lijst;
if ($_POST["van"]) {
	$sql .= " AND timestamp >= '".formatDate2($_POST["van"])."'";
}
if ($_POST["tot"]) {
	$sql .= " AND timestamp <= '".formatDate2($_POST["tot"])."'";
}
$result = mysqli_fetch_array($link->query($sql));
echo ' ('.$result[0].')';
echo '<br>';
echo 'Aantal stemmers';
$sql = "SELECT COUNT(DISTINCT(t.id)) FROM muzieklijst_stemmen s, muzieklijst_stemmers t WHERE s.stemmer_id = t.id AND s.lijst_id = ".$lijst;
if ($_POST["van"]) {
	$sql .= " AND timestamp >= '".formatDate2($_POST["van"])."'";
}
if ($_POST["tot"]) {
	$sql .= " AND timestamp <= '".formatDate2($_POST["tot"])."'";
}
$result = mysqli_fetch_array($link->query($sql));
echo ' ('.$result[0].')';



?>
						</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php
$sql = "SELECT t.timestamp, n.id, n.titel, n.artiest, s.stemmer_id, COUNT(*) as aantal FROM muzieklijst_stemmers t, muzieklijst_stemmen s, muzieklijst_nummers n WHERE s.nummer_id = n.id AND t.id = s.stemmer_id AND lijst_id = ".$lijst;
if ($_POST["van"]) {
	$sql .= " AND timestamp >= '".formatDate2($_POST["van"])."'";
}
if ($_POST["tot"]) {
	$sql .= " AND timestamp <= '".formatDate2($_POST["tot"])."'";
}
$sql .= " GROUP BY n.id ORDER BY aantal DESC";
$res = Muzieklijsten_Database::query($sql);
foreach ( $res as $entry ) {
	$nummer = new Nummer($entry['id']);
	echo get_stemresultaat_html_rij($muzieklijst, $nummer);
}
if ( $res->num_rows < 6 ) {
	echo '<tr style="background-color: #fff;"><td><br><br><br><br><br><br><br><br></td></tr>'."\n";
}


//$result = $link->query($sql);
//$rows = mysqli_num_rows($result);
//$count = 1;
//while ($r = mysqli_fetch_array($result)) {
//	$sql3 = "SELECT nummer_id FROM muzieklijst_stemmen WHERE nummer_id = ".$r["id"]." AND lijst_id = ".$lijst." AND behandeld = 1 LIMIT 1";
//	$result3 = mysqli_fetch_array($link->query($sql3));
//	echo '				<tr id="song_'.$r["id"].'" data-toggle="collapse" data-target="#'.$count.'" class="accordion-toggle hand'."\n";
//	if ($result3[0]) echo ' success';
//	echo '">'."\n";
//	echo '					<td style="text-align:center"><i class="fa fa-plus-square"></i></td>'."\n";
//	echo '					<td>'.$r["titel"].'</td>'."\n";
//	echo '					<td>'.$r["artiest"].'</td>'."\n";
//	echo '					<td class="text-center">'.$r["aantal"].'</td>'."\n";
//	echo '					<td>';
//	if ($_GET["admin"] == 1) echo '<div title="Nummer verwijderen"><i data-id="'.$r["id"].'" class="delSong fa fa-times fa-1 hand text-danger"></i></div>';
//	echo '					</td>'."\n";
//	echo '				</tr>'."\n";
//	echo '				<tr>'."\n";
//	echo '					<td colspan="4" class="hiddenRow">'."\n";
//	echo '						<div class="accordion-body collapse" id="'.$count.'">'."\n";
//	echo '							<div class="bs-callout bs-callout-info" style="margin:0px;">'."\n";
//	echo '								<table class="table">'."\n";
//	echo '								<thead>'."\n";
//	echo '									<tr>'."\n";
//	echo '										<th class="col-sm-2">Naam</th>'."\n";
//	if ($veld_woonplaats == 1 || $veld_adres == 1) {
//		echo '										<th class="col-sm-2">Woonplaats</th>'."\n";
//	}
//	if ($veld_adres == 1) {
//		echo '										<th class="col-sm-2">Adres</th>'."\n";
//	}
//	echo '										<th class="col-sm-2">Telefoonnummer</th>'."\n";
//		if ($veld_email == 1) {
//			echo '										<th class="col-sm-2">E-mailadres</th>'."\n";
//		}
//		if ($veld_uitzenddatum == 1) {
//			echo '										<th class="col-sm-2">Uitzenddatum</th>'."\n";
//		}
//		if ($veld_vrijekeus == 1) {
//			echo '										<th class="col-sm-2">Vrije keuze</th>'."\n";
//		}
//	echo '										<th class="col-sm-2">Aangevraagd op</th>'."\n";
//	echo '										<th class="col-sm-2">Toelichting</th>'."\n";
//	echo '										<th class="col-sm-1 text-center" align="center">Behandeld</th>'."\n";
//	echo '										<th class="col-sm-1"></th>'."\n";
//	echo '									</tr>'."\n";
//	echo '								</thead>'."\n";
//	echo '								<tbody>'."\n";
//	$sql2 = "SELECT t.naam, t.woonplaats, t.adres, t.postcode, t.telefoonnummer, t.emailadres, t.uitzenddatum, t.vrijekeus, t.ip, t.timestamp, s.toelichting, s.behandeld, s.id FROM muzieklijst_stemmen s, muzieklijst_stemmers t WHERE s.stemmer_id = t.id AND s.nummer_id = ".$r["id"]." AND lijst_id = ".$lijst;
//	if ($_POST["van"]) {
//		$sql2 .= " AND timestamp >= '".formatDate2($_POST["van"])."'";
//	}
//	if ($_POST["tot"]) {
//		$sql2 .= " AND timestamp <= '".formatDate2($_POST["tot"])."'";
//	}
//	$sql2 .= " ORDER BY timestamp DESC";
//	$result2 = $link->query($sql2);
//	while ($r2 = mysqli_fetch_array($result2)) {
//		echo '								<tr';
//		if ($r2["behandeld"] == 1) echo ' class="success"';
//		echo ' id="row_'.$r2["id"].'">'."\n";
//		echo '									<td title="'.$r2["ip"].'">'.$r2["naam"].'</td>'."\n";
//		if ($veld_woonplaats == 1) {
//			echo '									<td>'.$r2["woonplaats"].'</td>'."\n";
//		}
//		if ($veld_adres == 1) {
//			echo '									<td>'.$r2["adres"]."<br>".$r2["postcode"].'</td>'."\n";
//		}
//		echo '									<td>'.$r2["telefoonnummer"].'</td>'."\n";
//		if ($veld_email == 1) {
//			echo '									<td>'.$r2["emailadres"].'</td>'."\n";
//		}
//		if ($veld_uitzenddatum == 1) {
//			echo '									<td>'.$r2["uitzenddatum"].'</td>'."\n";
//		}
//		if ($veld_vrijekeus == 1) {
//			echo '									<td>'.$r2["vrijekeus"].'</td>'."\n";
//		}
//		echo '									<td>'.formatDate($r2["timestamp"]).'</td>'."\n";
//		echo '									<td>'.$r2["toelichting"].'</td>'."\n";
//		echo '									<td class="text-center hand" onclick="$(\'#'.$r2["id"].'\').trigger(\'click\');"><input type="checkbox" class="hand" id="'.$r2["id"].'"';
//		if ($r2["behandeld"] == 1) echo ' checked';
//		echo ' onclick="$(\'#'.$r2["id"].'\').trigger(\'click\');"></td>'."\n";
//		echo '									<td><div title="Resultaat verwijderen"><i class="fa fa-times fa-1 hand text-danger" onclick="if (confirm(\'Dit resultaat verwijderen?\')) { delResult(\''.$r2["id"].'\'); }"></i></div></td>'."\n";
//		echo '								</tr>'."\n";
//	}
//	echo '								</table>'."\n";
//	echo '							</div>'."\n";
//	echo '						</div>'."\n";
//	echo '					</td>'."\n";
//	echo '				</tr>'."\n";
//	$count++;
//}
//if ($rows < 6) {
//	echo '<tr style="background-color: #fff;"><td><br><br><br><br><br><br><br><br></td></tr>'."\n";
//}
?>                                
				</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

</form>

<script type='text/javascript'>
$(document).ready(function() {
	$('tr').on('shown.bs.collapse', function(){
		$(this).prev('tr').find(".fa-plus-square").removeClass("fa-plus-square").addClass("fa-minus-square");
		$("#iframeHelper", window.parent.document).height( $("#iframeHelper", window.parent.document).contents().find("html").height() );
	}).on('hidden.bs.collapse', function(){
		$(this).prev('tr').find(".fa-minus-square").removeClass("fa-minus-square").addClass("fa-plus-square");
		$("#iframeHelper", window.parent.document).height( $("#iframeHelper", window.parent.document).contents().find("html").height() );
	});
	
	$("#iframeHelper", window.parent.document).height( $("#iframeHelper", window.parent.document).contents().find("html").height() );
	
	$("input:checkbox").change(function() { 
		if($(this).is(":checked")) { 
			$(this).parent().parent().addClass('success'); 
			$.ajax({
				url: 'resultaten.php',
				type: 'POST',
				data: { id:$(this).attr("id"), behandeld:"1" }
			});
		} else {
			$(this).parent().parent().removeClass('success'); 
			$.ajax({
				url: 'resultaten.php',
				type: 'POST',
				data: { id:$(this).attr("id"), behandeld:"0" }
			});
		}
	}); 

	
	$(".delSong").click(function (event) {
		if (confirm('Dit nummer, inclusief alle reacties hierop, verwijderen uit de stemlijst?')) { 
			delSong($(this).data("id"));
		}
		 event.stopPropagation();
	});

	
});


function delResult(thisid) {
	$.ajax({
		url: 'resultaten.php',
		type: 'POST',
		data: { id:thisid, delresult:"1" }
	});
	$("#row_"+thisid).hide();
}

function delSong(thisid) {
	$.ajax({
		url: 'resultaten.php',
		type: 'POST',
		data: { id:thisid, lijst:<?php echo $lijst; ?>, delsong:"1" }
	});
	$("#song_"+thisid).hide();
}


$(function () {
        $("#datetimepicker1").datetimepicker({
			locale: 'nl',
			calendarWeeks: true,
			format: 'DD-MM-YYYY' 
		});
        $("#datetimepicker2").datetimepicker({
			locale: 'nl',
			calendarWeeks: true,
			format: 'DD-MM-YYYY' 
		});
        $("#datetimepicker1").on("dp.change",function (e) {
            $('#datetimepicker2').data("DateTimePicker").minDate(e.date);
        });
        $("#datetimepicker2").on("dp.change",function (e) {
            $('#datetimepicker1').data("DateTimePicker").maxDate(e.date);
        });
});




</script>
</body>
</html>
