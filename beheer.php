<?php

require_once __DIR__.'/include/include.php';

/**
 * Voert ajaxrequests uit.
 */
function ajax() {
	$is_actief = $_POST['is_actief'] == 1;
	$naam = trim($_POST['naam']);
	$email = trim($_POST['email']);
	$veld_telefoonnummer = $_POST['veld_telefoonnummer'] == 1;
	$veld_email = $_POST['veld_email'] == 1;
	$veld_woonplaats = $_POST['veld_woonplaats'] == 1;
	$veld_adres = $_POST['veld_adres'] == 1;
	$veld_uitzenddatum = $_POST['veld_uitzenddatum'] == 1;
	$veld_vrijekeus = $_POST['veld_vrijekeus'] == 1;
	$recaptcha = $_POST['recaptcha'] == 1;
	$minkeuzes = (int)$_POST['minkeuzes'];
	$maxkeuzes = (int)$_POST['maxkeuzes'];
	$stemmen_per_ip = (int)$_POST['stemmen_per_ip'];
	if ( $stemmen_per_ip === 0 ) {
		$stemmen_per_ip = null;
	}
	$artiest_eenmalig = $_POST['artiest_eenmalig'] == 1;
	
	if ( $_POST['type'] === 'edit' ) {
		$lijst = new Muzieklijst((int)$_POST['lijst']);
		Muzieklijsten_Database::updateMulti('muzieklijst_lijsten', [
			'naam' => $naam,
			'actief' => $is_actief,
			'minkeuzes' => $minkeuzes,
			'maxkeuzes' => $maxkeuzes,
			'stemmen_per_ip' => $stemmen_per_ip,
			'artiest_eenmalig' => $artiest_eenmalig,
			'veld_telefoonnummer' => $veld_telefoonnummer,
			'veld_email' => $veld_email,
			'veld_woonplaats' => $veld_woonplaats,
			'veld_adres' => $veld_adres,
			'veld_uitzenddatum' => $veld_uitzenddatum,
			'veld_vrijekeus' => $veld_vrijekeus,
			'recaptcha' => $recaptcha,
			'email' => $email
		], sprintf('id = %d', $lijst->get_id()));
	}
	if ( $_POST['type'] === 'delete' ) {
		$lijst = new Muzieklijst((int)$_POST['lijst']);
		$lijst->remove();
	}
	if ( $_POST['type'] === 'new' ) {
		Muzieklijsten_Database::insertMulti('muzieklijst_lijsten', [
			'naam' => $naam,
			'actief' => $is_actief,
			'minkeuzes' => $minkeuzes,
			'maxkeuzes' => $maxkeuzes,
			'stemmen_per_ip' => $stemmen_per_ip,
			'artiest_eenmalig' => $artiest_eenmalig,
			'veld_telefoonnummer' => $veld_telefoonnummer,
			'veld_email' => $veld_email,
			'veld_woonplaats' => $veld_woonplaats,
			'veld_adres' => $veld_adres,
			'veld_uitzenddatum' => $veld_uitzenddatum,
			'veld_vrijekeus' => $veld_vrijekeus,
			'recaptcha' => $recaptcha,
			'email' => $email
		]);
	}
}

if ( is_dev() ) {
	error_reporting(E_ALL & ~E_NOTICE);
}

login();

if ( array_key_exists('type', $_POST) ) {
	ajax();
	exit();
}

$link = Muzieklijsten_Database::getDB();
$result = $link->query("SET NAMES 'utf8'");

$lijst_id = (int)$_GET['lijst'];

if ( $lijst_id != 0 ) {
	$lijst = new Muzieklijst($lijst_id);
	
	$is_actief = $lijst->is_actief();
	$naam = $lijst->get_naam();
	$email = $lijst->get_notificatie_email_adressen();
	$veld_telefoonnummer = $lijst->heeft_veld_telefoonnummer();
	$veld_email = $lijst->heeft_veld_email();
	$veld_woonplaats = $lijst->heeft_veld_woonplaats();
	$veld_adres = $lijst->heeft_veld_adres();
	$veld_uitzenddatum = $lijst->heeft_veld_uitzenddatum();
	$veld_vrijekeus = $lijst->heeft_veld_vrijekeus();
	$recaptcha = $lijst->heeft_gebruik_recaptcha();
	$minkeuzes = $lijst->get_minkeuzes();
	$maxkeuzes = $lijst->get_maxkeuzes();
	$stemmen_per_ip = $lijst->get_max_stemmen_per_ip();
	$artiest_eenmalig = $lijst->is_artiest_eenmalig();
} else {
	$naam = '';
	$is_actief = true;
	$email = [];
	$veld_telefoonnummer = false;
	$veld_email = false;
	$veld_woonplaats = true;
	$veld_adres = false;
	$veld_uitzenddatum = false;
	$veld_vrijekeus = false;
	$recaptcha = true;
	$minkeuzes = 0;
	$maxkeuzes = 0;
	$stemmen_per_ip = null;
	$artiest_eenmalig = false;
}
if ( $minkeuzes === 0 ) {
	$minkeuzes = '';
}
if ( $maxkeuzes === 0 ) {
	$maxkeuzes = '';
}
if ( $stemmen_per_ip === null ) {
	$stemmen_per_ip = '';
}

?>
<div class="container-fluid">

<form class="form-horizontal" role="form" method="post" id="manage">
	<div style="height: 10px; clear: both;"></div>
	
	<div class="form-group">
		<label class="control-label col-sm-2" for="naam">Naam lijst:</label>
		<div class="col-sm-10">	
 			<input type="text" class="form-control" id="naam" name="naam" value="<?php echo $naam; ?>">
		</div>
	</div>
	
	<div style="height: 10px; clear: both;"></div>

	<div class="form-group">
		<label class="control-label col-sm-2" for="email">E-mailadressen voor reacties: <span class="glyphicon glyphicon-info-sign" title="Meerdere e-mailadressen scheiden door een komma ( , )" style="cursor:pointer;"></span></label>
		<div class="col-sm-10">
		  <input type="email" class="form-control" id="email" name="email" value="<?php echo implode(',', $email); ?>">
		</div>
	</div>
	
	<div style="height: 10px; clear: both;"></div>
	
	<div class="form-group">
		<label class="control-label col-sm-2" for="velden">Velden tonen:</label>
		<div class="col-sm-10">
			<input type="checkbox" id="veld_telefoonnummer" value="1" name="veld_telefoonnummer"<?php if ( $veld_telefoonnummer ) { echo " checked"; } ?>> Telefoonnummer <br>
			<input type="checkbox" id="veld_email" value="1" name="veld_email"<?php if ( $veld_email ) { echo " checked"; } ?>> E-mailadres <br>
			<input type="checkbox" id="veld_woonplaats" value="1" name="veld_woonplaats"<?php if ( $veld_woonplaats ) { echo " checked"; } ?>> Woonplaats <br>
			<input type="checkbox" id="veld_adres" value="1" name="veld_adres"<?php if ( $veld_adres ) { echo " checked"; } ?>> Adres <br>
			<input type="checkbox" id="veld_uitzenddatum" value="1" name="veld_uitzenddatum"<?php if ( $veld_uitzenddatum ) { echo " checked"; } ?>> Uitzenddatum <br>
			<input type="checkbox" id="veld_vrijekeus" value="1" name="veld_vrijekeus"<?php if ( $veld_vrijekeus ) { echo " checked"; } ?>> Vrije keuze <br>
			<input type="checkbox" id="recaptcha" value="1" name="recaptcha"<?php if ( $recaptcha ) { echo " checked"; } ?>> Re-Captcha <br>
		</div>
	</div>

	<div style="height: 10px; clear: both;"></div>
	
	<div class="form-group">
		<label class="control-label col-sm-2" for="velden">Opties:</label>
		<div class="col-sm-10">
 			Minimaal aantal keuzes <input type="number" class="form-control" id="minkeuzes" name="minkeuzes" value="<?php echo $minkeuzes; ?>"><br>
			Maximaal aantal keuzes (leeglaten voor onbeperkt) <input type="number" class="form-control" id="maxkeuzes" name="maxkeuzes" value="<?php echo $maxkeuzes; ?>"><br>
			Aantal stemmen per IP (leeglaten voor onbeperkt) <input type="number" class="form-control" id="stemmen_per_ip" name="stemmen_per_ip" value="<?php echo $stemmen_per_ip; ?>"><br>
			<input type="checkbox" id="is_actief" value="1" name="is_actief"<?php if ( $is_actief ) { echo " checked"; } ?>> Lijst is actief (stemmen staat aan)<br>
			<input type="checkbox" id="artiest_eenmalig" value="1" name="artiest_eenmalig"<?php if ( $artiest_eenmalig ) { echo " checked"; } ?>> Artiesten eenmalig te selecteren <br>
			
		</div>
	</div>
	
	<div style="height: 10px; clear: both;"></div>
	
	<div class="form-group">
		<div class="col-sm-2"></div>
		<div class="col-sm-10">
 			<input type="button" id="submit_manage" name="submit_manage" class="btn btn-primary" value="Opslaan">
		</div>
	</div>

	<?php if ( $lijst_id != 0 ) {?>
	<div class="form-group">
		<div class="col-sm-2"></div>
		<div class="col-sm-10">
 			<input type="button" class="btn btn-danger" value="Lijst verwijderen" onclick="if (confirm('Deze lijst verwijderen?\nOok alle stemmen op nummers uit deze lijst worden verwijderd.')) { delete_list(<?php echo $lijst_id; ?>) };">
		</div>
	</div>
	
	<div style="height: 10px; clear: both;"></div>
	<input type="hidden" name="lijst" value="<?php echo $lijst_id; ?>">
	<input type="hidden" name="type" value="edit">
	
	<?php } else { ?>
	
	<input type="hidden" name="type" value="new">
	
	<?php } ?>
</form>
</div>	

<script>
$("#submit_manage").click(function() {
	var data = $('#manage').serialize();
	$.ajax({
		type: 'POST',
		url: 'beheer.php',
		data: data,
		success: function( data ) {
			$('#beheer').modal('hide');
			location.reload();
		}
	});
});

function delete_list(id) {
	$.ajax({
		type: 'POST',
		url: 'beheer.php',
		data: { lijst:id, type:'delete' },
		success: function( data ) {
			$('#beheer').modal('hide');
			location.reload();
		}
	});
}
</script>
