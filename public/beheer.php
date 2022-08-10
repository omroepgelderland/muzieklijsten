<?php

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

login();

try {
	$lijst = Lijst::maak_uit_request(INPUT_GET);
	$lijst_id = $lijst->get_id();
	$naam = htmlspecialchars($lijst->get_naam());
	$is_actief = $lijst->is_actief();
	$minkeuzes = $lijst->get_minkeuzes();
	$maxkeuzes = $lijst->get_maxkeuzes();
	$stemmen_per_ip = $lijst->get_max_stemmen_per_ip();
	$artiest_eenmalig = $lijst->is_artiest_eenmalig();
	$veld_telefoonnummer = $lijst->heeft_veld_telefoonnummer();
	$veld_email = $lijst->heeft_veld_email();
	$veld_woonplaats = $lijst->heeft_veld_woonplaats();
	$veld_adres = $lijst->heeft_veld_adres();
	$veld_uitzenddatum = $lijst->heeft_veld_uitzenddatum();
	$veld_vrijekeus = $lijst->heeft_veld_vrijekeus();
	$recaptcha = $lijst->heeft_gebruik_recaptcha();
	$email = htmlspecialchars(implode(',', $lijst->get_notificatie_email_adressen()));
	$bedankt_tekst = htmlspecialchars($lijst->get_bedankt_tekst());
} catch ( Muzieklijsten_Exception $e ) {
	$lijst_id = '';
	$naam = '';
	$is_actief = true;
	$minkeuzes = 1;
	$maxkeuzes = 1;
	$stemmen_per_ip = null;
	$artiest_eenmalig = false;
	$veld_telefoonnummer = false;
	$veld_email = false;
	$veld_woonplaats = true;
	$veld_adres = false;
	$veld_uitzenddatum = false;
	$veld_vrijekeus = false;
	$recaptcha = true;
	$email = '';
	$bedankt_tekst = 'Bedankt voor je keuze';
}
if ( $stemmen_per_ip === null ) {
	$stemmen_per_ip = '';
}

$is_actief_checked = $is_actief
	? ' checked'
	: '';
$artiest_eenmalig_checked = $artiest_eenmalig
	? ' checked'
	: '';
$veld_telefoonnummer_checked = $veld_telefoonnummer
	? ' checked'
	: '';
$veld_email_checked = $veld_email
	? ' checked'
	: '';
$veld_woonplaats_checked = $veld_woonplaats
	? ' checked'
	: '';
$veld_adres_checked = $veld_adres
	? ' checked'
	: '';
$veld_uitzenddatum_checked = $veld_uitzenddatum
	? ' checked'
	: '';
$veld_vrijekeus_checked = $veld_vrijekeus
	? ' checked'
	: '';
$recaptcha_checked = $recaptcha
	? ' checked'
	: '';
?>
<div class="container-fluid beheercontainer">
	<form class="form-horizontal" role="form" method="post" id="beheer-lijst">
		<input type="hidden" name="lijst" value="<?php echo $lijst_id; ?>">
		<div class="separator"></div>
		<div class="form-group">
			<label class="control-label col-sm-2" for="naam">Naam lijst:</label>
			<div class="col-sm-10">	
				<input type="text" class="form-control" id="naam" name="naam" value="<?php echo $naam; ?>">
			</div>
		</div>
		<div class="separator"></div>
		<div class="form-group">
			<label class="control-label col-sm-2" for="email">E-mailadressen voor reacties: <span class="glyphicon glyphicon-info-sign pointer" title="Meerdere e-mailadressen scheiden door een komma ( , )"></span></label>
			<div class="col-sm-10">
				<input type="text" class="form-control" id="email" name="email" value="<?php echo $email; ?>">
			</div>
		</div>
		<div class="separator"></div>
		<div class="form-group">
			<label class="control-label col-sm-2" for="bedankt-tekst">Tekst voor bezoekers na het stemmen: <span class="glyphicon glyphicon-info-sign pointer" title="De tekst die de stemmers te zien krijgen nadat ze hun stem hebben ingediend."></span></label>
			<div class="col-sm-10">
				<input type="text" class="form-control" id="bedankt-tekst" name="bedankt-tekst" value="<?php echo $bedankt_tekst; ?>">
			</div>
		</div>
		<div class="separator"></div>
		<div class="form-group">
			<label class="control-label col-sm-2" for="velden">Velden tonen:</label>
			<div class="col-sm-10">
				<input type="checkbox" id="veld-telefoonnummer" name="veld-telefoonnummer"<?php echo $veld_telefoonnummer_checked; ?>> Telefoonnummer <br>
				<input type="checkbox" id="veld-email" name="veld-email"<?php echo $veld_email_checked; ?>> E-mailadres <br>
				<input type="checkbox" id="veld-woonplaats" name="veld-woonplaats"<?php echo $veld_woonplaats_checked; ?>> Woonplaats <br>
				<input type="checkbox" id="veld-adres" name="veld-adres"<?php echo $veld_adres_checked; ?>> Adres <br>
				<input type="checkbox" id="veld-uitzenddatum" name="veld-uitzenddatum"<?php echo $veld_uitzenddatum_checked; ?>> Uitzenddatum <br>
				<input type="checkbox" id="veld-vrijekeus" name="veld-vrijekeus"<?php echo $veld_vrijekeus_checked; ?>> Vrije keuze <br>
				<input type="checkbox" id="recaptcha" name="recaptcha"<?php echo $recaptcha_checked; ?>> Re-Captcha <br>
			</div>
		</div>
		<div class="separator"></div>
		<div class="form-group">
			<label class="control-label col-sm-2" for="velden">Opties:</label>
			<div class="col-sm-10">
				Minimaal aantal keuzes <input type="number" class="form-control" id="minkeuzes" name="minkeuzes" min="1" value="<?php echo $minkeuzes; ?>"><br>
				Maximaal aantal keuzes <input type="number" class="form-control" id="maxkeuzes" name="maxkeuzes" min="1" value="<?php echo $maxkeuzes; ?>"><br>
				Aantal stemmen per IP (leeglaten voor onbeperkt) <input type="number" class="form-control" id="stemmen-per-ip" name="stemmen-per-ip" min="1" value="<?php echo $stemmen_per_ip; ?>"><br>
				<input type="checkbox" id="is-actief" name="is-actief"<?php echo $is_actief_checked; ?>> Lijst is actief (stemmen staat aan)<br>
				<input type="checkbox" id="artiest-eenmalig" name="artiest-eenmalig"<?php $artiest_eenmalig_checked; ?>> Artiesten eenmalig te selecteren <br>
			</div>
		</div>
		<div class="separator"></div>
		<div class="form-group">
			<div class="col-sm-2"></div>
			<div class="col-sm-10">
				<input type="submit" class="btn btn-primary" value="Opslaan">
			</div>
		</div>
		<div class="form-group beheer-bestaand">
			<div class="col-sm-2"></div>
			<div class="col-sm-10">
				<input type="button" id="verwijder-lijst" class="btn btn-danger" value="Lijst verwijderen">
			</div>
		</div>
		<div class="separator beheer-bestaand"></div>
	</form>
</div>	
