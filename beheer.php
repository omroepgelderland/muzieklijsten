<?php

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Inloggen"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Je moet inloggen om deze pagina te kunnen zien.';
    exit;
} else {
	if (($_SERVER['PHP_AUTH_USER'] == "gld") AND ($_SERVER["PHP_AUTH_PW"] = "muziek=fijn")) {
	
	function makezero($val) {
		if ($val) {
			return $val;
		} else {
			return 0;
		}
	}
	
	function makenull($val) {
		if ($val) {
			return $val;
		} else {
			return 'NULL';
		}
	}
	
	
$link = mysqli_connect("localhost","w3omrpg","H@l*lOah","rtvgelderland") or die("Error " . mysqli_error($link));
$result = $link->query("SET NAMES 'utf8'");

$lijst = (int)$_POST['lijst'];
$id = (int)$_POST['id'];
$naam = $_POST['naam'];
$email = $_POST['email'];
$veld_telefoonnummer = (int)$_POST['veld_telefoonnummer'];
$veld_email = (int)$_POST['veld_email'];
$veld_woonplaats = (int)$_POST['veld_woonplaats'];
$veld_adres = (int)$_POST['veld_adres'];
$veld_uitzenddatum = (int)$_POST['veld_uitzenddatum'];
$veld_vrijekeus = (int)$_POST['veld_vrijekeus'];
$recaptcha = (int)$_POST['recaptcha'];
$minkeuzes = (int)$_POST['minkeuzes'];
$maxkeuzes = (int)$_POST['maxkeuzes'];
$stemmen_per_ip = (int)$_POST['stemmen_per_ip'];
$artiest_eenmalig = (int)$_POST['artiest_eenmalig'];
	
if ($lijst != 0) {
	$sql = sprintf(
		'UPDATE muzieklijst_lijsten SET naam = %s, email = %s, veld_telefoonnummer = %d, veld_email = %d, veld_woonplaats = %d, veld_adres = %d, 
	veld_uitzenddatum = %d, veld_vrijekeus = %d, recaptcha = %d,
	minkeuzes = %d, maxkeuzes = %d, stemmen_per_ip = %s, artiest_eenmalig = %d WHERE id = %d',
		mysqli_real_escape_string($naam),
		mysqli_real_escape_string($email),
		$veld_telefoonnummer,
		$veld_email,
		$veld_woonplaats,
		$veld_adres,
		$veld_uitzenddatum,
		$veld_vrijekeus,
		$recaptcha,
		$minkeuzes,
		$maxkeuzes,
		makenull($stemmen_per_ip),
		$artiest_eenmalig,
		$lijst
	);
	
	$sql = "UPDATE muzieklijst_lijsten SET naam = '".mysqli_real_escape_string($naam)."', email = '".mysqli_real_escape_string($email)."', veld_telefoonnummer = ".$veld_telefoonnummer.", veld_email = ".$veld_email.", veld_woonplaats = ".$veld_woonplaats.", veld_adres = ".$veld_adres.", 
	veld_uitzenddatum = ".$veld_uitzenddatum.", veld_vrijekeus = ".$veld_vrijekeus.", recaptcha = ".$recaptcha.",
	minkeuzes = ".$minkeuzes.", maxkeuzes = ".$maxkeuzes.", stemmen_per_ip = ".makenull($stemmen_per_ip).", artiest_eenmalig = ".$artiest_eenmalig." WHERE id = ".$lijst;
	$result = $link->query($sql);
	exit();
}

if ($_POST["type"] == "delete") {
	$sql = "DELETE FROM muzieklijst_lijsten WHERE id = ".$id;
	$result = $link->query($sql);
	$sql = "DELETE FROM muzieklijst_nummers_lijst WHERE lijst_id = ".$id;
	$result = $link->query($sql);
	$sql = "DELETE FROM muzieklijst_stemmen WHERE lijst_id = ".$id;
	$result = $link->query($sql);
	exit();
}

if ($_POST["type"] == "new") {
	$sql = "INSERT INTO muzieklijst_lijsten (naam, email, veld_telefoonnummer, veld_email, veld_woonplaats, veld_adres, veld_uitzenddatum, veld_vrijekeus, recaptcha, minkeuzes, maxkeuzes, stemmen_per_ip, artiest_eenmalig) 
	VALUES ('".mysqli_real_escape_string($naam)."', '".mysqli_real_escape_string($email)."', ".$veld_telefoonnummer.", ".$veld_email.", ".$veld_woonplaats.", ".$veld_adres.", ".$veld_uitzenddatum.", ".$veld_vrijekeus.", ".$recaptcha.", 
	".$minkeuzes.", ".$maxkeuzes.", ".makenull($stemmen_per_ip).", ".$artiest_eenmalig.")";
	$result = $link->query($sql);
	exit();
}

$lijst = (int)$_GET['lijst'];

if ( $lijst != 0 ) {
	
	$sql = "SELECT naam FROM muzieklijst_lijsten WHERE id = ".$lijst;
	$result = mysqli_fetch_array($link->query($sql));
	$naam = $result[0];
	
	$sql = "SELECT email FROM muzieklijst_lijsten WHERE id = ".$lijst;
	$result = mysqli_fetch_array($link->query($sql));
	$email = $result[0];
	
	$sql = "SELECT veld_telefoonnummer FROM muzieklijst_lijsten WHERE id = ".$lijst;
	$result = mysqli_fetch_array($link->query($sql));
	$veld_telefoonnummer = $result[0];
	
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
	
	$sql = "SELECT recaptcha FROM muzieklijst_lijsten WHERE id = ".$lijst;
	$result = mysqli_fetch_array($link->query($sql));
	$recaptcha = $result[0];
	
	$sql = "SELECT minkeuzes FROM muzieklijst_lijsten WHERE id = ".$lijst;
	$result = mysqli_fetch_array($link->query($sql));
	$minkeuzes = $result[0];
	
	$sql = "SELECT maxkeuzes FROM muzieklijst_lijsten WHERE id = ".$lijst;
	$result = mysqli_fetch_array($link->query($sql));
	$maxkeuzes = $result[0];
	
	$sql = "SELECT stemmen_per_ip FROM muzieklijst_lijsten WHERE id = ".$lijst;
	$result = mysqli_fetch_array($link->query($sql));
	$stemmen_per_ip = $result[0];
	
	$sql = "SELECT artiest_eenmalig FROM muzieklijst_lijsten WHERE id = ".$lijst;
	$result = mysqli_fetch_array($link->query($sql));
	$artiest_eenmalig = $result[0];

}

?>
<div class="container-fluid">

<form class="form-horizontal" role="form" method="post" id="manage">
	<div style="height: 10px; clear: both;"></div>
	
	<div class="form-group">
		<label class="control-label col-sm-2" for="naam">Naam lijst:</label>
		<div class="col-sm-10">	
 			<input type="text" class="form-control" id="naam" name="naam" value="<?php echo $naam;?>">
		</div>
	</div>
	
	<div style="height: 10px; clear: both;"></div>

	<div class="form-group">
		<label class="control-label col-sm-2" for="email">E-mailadres voor reacties: <span class="glyphicon glyphicon-info-sign" title="Meerdere e-mailadressen scheiden door een komma ( , )" style="cursor:pointer;"></span></label>
		<div class="col-sm-10">
 			<input type="email" class="form-control" id="email" name="email" value="<?php echo $email;?>">
		</div>
	</div>
	
	<div style="height: 10px; clear: both;"></div>
	
	<div class="form-group">
		<label class="control-label col-sm-2" for="velden">Velden tonen:</label>
		<div class="col-sm-10">
			<input type="checkbox" id="veld_telefoonnummer" value="1" name="veld_telefoonnummer"<?php if ($veld_telefoonnummer == 1) echo " checked";?>> Telefoonnummer <br />
 			<input type="checkbox" id="veld_email" value="1" name="veld_email"<?php if ($veld_email == 1) echo " checked";?>> E-mailadres <br />
			<input type="checkbox" id="veld_woonplaats" value="1" name="veld_woonplaats"<?php if ($veld_woonplaats == 1) echo " checked";?>> Woonplaats <br />
			<input type="checkbox" id="veld_adres" value="1" name="veld_adres"<?php if ($veld_adres == 1) echo " checked";?>> Adres <br />
			<input type="checkbox" id="veld_uitzenddatum" value="1" name="veld_uitzenddatum"<?php if ($veld_uitzenddatum == 1) echo " checked";?>> Uitzend datum <br />
			<input type="checkbox" id="veld_vrijekeus" value="1" name="veld_vrijekeus"<?php if ($veld_vrijekeus == 1) echo " checked";?>> Vrije keuze <br />
			<input type="checkbox" id="recaptcha" value="1" name="recaptcha"<?php if ($recaptcha == 1) echo " checked";?>> Re-Captcha <br />
		</div>
	</div>

	<div style="height: 10px; clear: both;"></div>
	
	<div class="form-group">
		<label class="control-label col-sm-2" for="velden">Opties:</label>
		<div class="col-sm-10">
 			Minimaal aantal keuzes <input type="text" class="form-control" id="minkeuzes" name="minkeuzes" value="<?php echo $minkeuzes;?>"><br />
			Maximaaal aantal keuzes <input type="text" class="form-control" id="maxkeuzes" name="maxkeuzes" value="<?php echo $maxkeuzes;?>"><br />
			Aantal stemmen per IP <input type="text" class="form-control" id="stemmen_per_ip" name="stemmen_per_ip" value="<?php echo $stemmen_per_ip;?>"><br />
			<input type="checkbox" id="artiest_eenmalig" value="1" name="artiest_eenmalig"<?php if ($artiest_eenmalig == 1) echo " checked";?>> Artiesten eenmalig te selecteren <br />
			
		</div>
	</div>
	
	<div style="height: 10px; clear: both;"></div>
	
	<div class="form-group">
		<div class="col-sm-2"></div>
		<div class="col-sm-10">
 			<input type="button" id="submit_manage" name="submit_manage" class="btn btn-primary" value="Opslaan">
		</div>
	</div>

	<?php if ( $lijst != 0 ) {?>
	<div class="form-group">
		<div class="col-sm-2"></div>
		<div class="col-sm-10">
 			<input type="button" class="btn btn-danger" value="Lijst verwijderen" onclick="if (confirm('Deze lijst verwijderen?\nOok alle stemmen op nummers uit deze lijst worden verwijderd.')) { delete_list(<?php echo $lijst; ?>) };">
		</div>
	</div>
	
	<div style="height: 10px; clear: both;"></div>
	<input type="hidden" name="lijst" value="<?php echo $lijst; ?>">
	
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
		data: { id:id, type:'delete' },
		success: function( data ) {
			$('#beheer').modal('hide');
			location.reload();
		}
	});
}
</script>


<?php
} 
}
?>
