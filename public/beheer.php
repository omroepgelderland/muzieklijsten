<?php

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

try {

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
		$veld_telefoonnummer_verplicht = $lijst->is_veld_telefoonnummer_verplicht();
		$veld_email = $lijst->heeft_veld_email();
		$veld_email_verplicht = $lijst->is_veld_email_verplicht();
		$veld_woonplaats = $lijst->heeft_veld_woonplaats();
		$veld_woonplaats_verplicht = $lijst->is_veld_woonplaats_verplicht();
		$veld_adres = $lijst->heeft_veld_adres();
		$veld_adres_verplicht = $lijst->is_veld_adres_verplicht();
		$veld_uitzenddatum = $lijst->heeft_veld_uitzenddatum();
		$veld_uitzenddatum_verplicht = $lijst->is_veld_uitzenddatum_verplicht();
		$veld_vrijekeus = $lijst->heeft_veld_vrijekeus();
		$veld_vrijekeus_verplicht = $lijst->is_veld_vrijekeus_verplicht();
		$recaptcha = $lijst->heeft_gebruik_recaptcha();
		$email = htmlspecialchars(implode(',', $lijst->get_notificatie_email_adressen()));
		$bedankt_tekst = htmlspecialchars($lijst->get_bedankt_tekst());
	} catch ( GeenLijstException $e ) {
		$lijst_id = '';
		$naam = '';
		$is_actief = true;
		$minkeuzes = 1;
		$maxkeuzes = 1;
		$stemmen_per_ip = null;
		$artiest_eenmalig = false;
		$veld_telefoonnummer = false;
		$veld_telefoonnummer_verplicht = false;
		$veld_email = false;
		$veld_email_verplicht = false;
		$veld_woonplaats = true;
		$veld_woonplaats_verplicht = true;
		$veld_adres = false;
		$veld_adres_verplicht = false;
		$veld_uitzenddatum = false;
		$veld_uitzenddatum_verplicht = false;
		$veld_vrijekeus = false;
		$veld_vrijekeus_verplicht = false;
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
	$veld_telefoonnummer_verplicht_checked = $veld_telefoonnummer_verplicht
		? ' checked'
		: '';
	$veld_telefoonnummer_verplicht_disabled = $veld_telefoonnummer
		? ''
		: ' disabled';
	$veld_email_checked = $veld_email
		? ' checked'
		: '';
	$veld_email_verplicht_checked = $veld_email_verplicht
		? ' checked'
		: '';
	$veld_email_verplicht_disabled = $veld_email
		? ''
		: ' disabled';
	$veld_woonplaats_checked = $veld_woonplaats
		? ' checked'
		: '';
	$veld_woonplaats_verplicht_checked = $veld_woonplaats_verplicht
		? ' checked'
		: '';
	$veld_woonplaats_verplicht_disabled = $veld_woonplaats
		? ''
		: ' disabled';
	$veld_adres_checked = $veld_adres
		? ' checked'
		: '';
	$veld_adres_verplicht_checked = $veld_adres_verplicht
		? ' checked'
		: '';
	$veld_adres_verplicht_disabled = $veld_adres
		? ''
		: ' disabled';
	$veld_uitzenddatum_checked = $veld_uitzenddatum
		? ' checked'
		: '';
	$veld_uitzenddatum_verplicht_checked = $veld_uitzenddatum_verplicht
		? ' checked'
		: '';
	$veld_uitzenddatum_verplicht_disabled = $veld_uitzenddatum
		? ''
		: ' disabled';
	$veld_vrijekeus_checked = $veld_vrijekeus
		? ' checked'
		: '';
	$veld_vrijekeus_verplicht_checked = $veld_vrijekeus_verplicht
		? ' checked'
		: '';
	$veld_vrijekeus_verplicht_disabled = $veld_vrijekeus
		? ''
		: ' disabled';
	$recaptcha_checked = $recaptcha
		? ' checked'
		: '';

	$query = <<<EOT
	SELECT
		v.id,
		ev.lijst_id IS NOT NULL AS tonen,
		ev.verplicht,
		v.label
	FROM velden v
	LEFT JOIN lijsten_velden ev ON
		v.id = ev.veld_id
		AND ev.lijst_id = 31;
	EOT;
	$kolom1 = '';
	$kolom2 = '';
	foreach( DB::query($query) as ['id' => $id, 'tonen' => $tonen, 'verplicht' => $verplicht, 'label' => $label] ) {
		$tonen_checked = $tonen ? ' checked': '';
		$tonen_hidden_value = $tonen ? 'true': 'false';
		$verplicht_checked = $verplicht ? ' checked': '';
		$verplicht_disabled = !$tonen ? ' disabled': '';
		$verplicht_hidden_value = $verplicht ? 'true' : 'false';
		$label = htmlspecialchars($label);
		$kolom1 .= <<<EOT
		<input
			type="checkbox"
			id="veld-{$id}"
			name="veld-{$id}"
			data-input-verplicht="veld-{$id}-verplicht"
			data-hidden-id="veld-{$id}-hidden"
			{$tonen_checked}
			class="check-met-hidden">
		<input
			id="veld-{$id}-hidden"
			type="hidden"
			name="velden[{$id}][tonen]"
			value={$tonen_hidden_value}>
		{$label}<br>
		EOT;
		$kolom2 .= <<<EOT
		<input
			type="checkbox"
			id="veld-{$id}-verplicht"
			name="veld-{$id}-verplicht"
			data-hidden-id="veld-{$id}-verplicht-hidden"
			{$verplicht_checked}
			{$verplicht_disabled}
			class="check-met-hidden">
		<input
			id="veld-{$id}-verplicht-hidden"
			type="hidden"
			name="velden[{$id}][verplicht]"
			value="{$verplicht_hidden_value}">
		Verplicht<br>
		EOT;
	}
} catch ( \Throwable $e ) {
	Log::err($e);
	throw $e;
}

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
			<div class="col-sm-2">
				<?php echo $kolom1; ?>
				<input type="checkbox" id="recaptcha" name="recaptcha"<?php echo $recaptcha_checked; ?>> Re-Captcha<br>
			</div>
			<div class="col-sm-8">
				<?php echo $kolom2; ?>
			</div>
		</div>
		<div class="separator"></div>
		<div class="form-group">
			<label class="control-label col-sm-2" for="velden">Opties:</label>
			<div class="col-sm-10">
				Minimum aantal keuzes <input type="number" class="form-control" id="minkeuzes" name="minkeuzes" min="1" value="<?php echo $minkeuzes; ?>"><br>
				Maximum aantal keuzes <input type="number" class="form-control" id="maxkeuzes" name="maxkeuzes" min="1" value="<?php echo $maxkeuzes; ?>"><br>
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
