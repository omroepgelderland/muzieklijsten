<?php

function is_captcha_ok() {
	if ( $_SESSION['captcha'] != 1 ) {
		return true;
	}
	require('recaptcha/src/autoload.php');
	$recaptcha = new \ReCaptcha\ReCaptcha("6LdH7wsTAAAAADDnMKZ4g-c6f125Ftr0JQR-BDQp");
	$resp = $recaptcha->verify($_POST['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);
	return $resp->isSuccess();
}

function is_max_stemmen_per_ip_bereikt( $db, $lijst_id ) {
	$sql = sprintf(
		'SELECT l.stemmen_per_ip IS NOT NULL AND COUNT(DISTINCT stemmers.id) >= l.stemmen_per_ip FROM muzieklijst_stemmers stemmers JOIN muzieklijst_stemmen stemmen ON stemmen.stemmer_id = stemmers.id JOIN muzieklijst_lijsten l ON l.id = %d AND stemmen.lijst_id = l.id WHERE stemmers.ip = "%s"',
		$lijst_id,
		$_SERVER['REMOTE_ADDR']
	);
	$res = $db->query($sql)->fetch_row()[0];
	return ( $res == 1 );
}

$link = mysqli_connect("localhost","w3omrpg","H@l*lOah","rtvgelderland") or die("Error " . mysqli_error($link));
$result = $link->query("SET NAMES 'utf8'");
session_start();

include 'Mail.php';
include 'Mail/mime.php' ;

$lijst = (int)$_POST['lijst'];

if ($_POST['session']) {
	
	if ( is_captcha_ok() ) {
		//$sql = "INSERT INTO muzieklijst_stemmers (naam, adres, postcode, woonplaats, telefoonnummer, emailadres) VALUES ('".addslashes($_POST["naam"])."', '".addslashes($_POST["adres"])."', '".addslashes($_POST["postcode"])."', '".addslashes($_POST["woonplaats"])."', '".addslashes($_POST["telefoonnummer"])."', '".addslashes($_POST["email"])."')";
		// muzieklijst_lijsten.stemmen_per_ip
		// muzieklijst_stemmen.ip
		// $_SERVER["REMOTE_ADDR"]
		if ( !is_max_stemmen_per_ip_bereikt($link, $lijst) ) {
			$sql = "INSERT INTO muzieklijst_stemmers (naam, woonplaats, adres, postcode, telefoonnummer, emailadres, uitzenddatum, vrijekeus, ip) VALUES ('" . addslashes($_POST["naam"]) . "', '" . addslashes($_POST["woonplaats"]) . "', '" . addslashes($_POST["adres"]) . "', '" . addslashes($_POST["postcode"]) . "', '" . addslashes($_POST["telefoonnummer"]) . "', '" . addslashes($_POST["veld_email"]) . "', '" . addslashes($_POST["veld_uitzenddatum"]) . "', '" . addslashes($_POST["veld_vrijekeus"]) . "', '" . $_SERVER["REMOTE_ADDR"] . "')";
			$result = $link->query($sql);
			$last_id = $link->insert_id;

			$mailcontent = "Ontvangen van:\n\n";
			$mailcontent .= "Naam: " . $_POST["naam"] . "\n";
			$mailcontent .= "Woonplaats: " . $_POST["woonplaats"] . "\n";
			$mailcontent .= "Adres: " . $_POST["adres"] . "\n";
			$mailcontent .= "Postcode: " . $_POST["postcode"] . "\n";
			if ( $_SESSION["veld_telefoonnummer"] == 1 )
				$mailcontent .= "Telefoonnummer: " . $_POST["telefoonnummer"] . "\n";
			if ( $_SESSION["veld_email"] == 1 )
				$mailcontent .= "E-mailadres: " . $_POST["veld_email"] . "\n";
			if ( $_SESSION["veld_uitzenddatum"] == 1 )
				$mailcontent .= "Uitzenddatum: " . $_POST["veld_uitzenddatum"] . "\n";
			if ( $_SESSION["veld_vrijekeus"] == 1 )
				$mailcontent .= "Vrije keuze: " . $_POST["veld_vrijekeus"] . "\n";
			$mailcontent .= "\n";

			if ( is_array($_POST["id"]) ) {
				foreach ( $_POST["id"] as $key => $value ) {
					$value = (int) $value;
					$sql = "INSERT INTO muzieklijst_stemmen (nummer_id, lijst_id, stemmer_id, toelichting) VALUES (" . $value . ", " . $lijst . ", " . $last_id . ", '" . addslashes($_POST["id_" . $value]) . "');";
					$result = $link->query($sql);

					$sql = "SELECT titel, artiest FROM muzieklijst_nummers WHERE id = " . $value;
					$result = $link->query($sql);
					while ( $r = mysqli_fetch_array($result) ) {
						$mailcontent .= utf8_encode($r["titel"]) . ' - ' . utf8_encode($r["artiest"]) . "\n";
						$mailcontent .= "Toelichting: " . $_POST["id_" . $value] . "\n\n";
					}
				}
			}


			$sql = "SELECT email FROM muzieklijst_lijsten WHERE id = " . $lijst;
			$result = mysqli_fetch_array($link->query($sql));
			if ( $result[0] != "" ) {
				$sql = "SELECT naam FROM muzieklijst_lijsten WHERE id = " . $lijst;
				$result2 = mysqli_fetch_array($link->query($sql));
				if ( $_SESSION["veld_uitzenddatum"] == 1 ) {
					$topic = "Er is gestemd - " . $result2[0] . " - Uitzenddatum: " . $_POST["veld_uitzenddatum"];
				} else {
					$topic = "Er is gestemd - " . $result2[0];
				}

				//mail($result[0], "test - ".$topic, $mailcontent, "From: Omroep Gelderland <omroep@gld.nl>\n");






				$crlf = "\n";
				$headers = [
					'From' => 'Omroep Gelderland <omroep@gld.nl>',
					'To' => $result[0],
					'Subject' => $topic
				];
				$mime = new Mail_mime($crlf);
				$mime->setTXTBody($mailcontent);
				$mime_params = [
					'text_encoding' => '7bit',
					'text_charset' => 'UTF-8',
					'html_charset' => 'UTF-8',
					'head_charset' => 'UTF-8'
				];
				$body = $mime->get($mime_params);
				$headers = $mime->headers($headers);
				$params = [
					'sendmail_path' => '/usr/lib/sendmail'
				];
				$mail_obj = Mail::factory('sendmail', $params);
				$mail_obj->send($result[0], $headers, $body);
			}
		}

		//echo '<div class="alert alert-success" role="alert" style="clear: both; text-align: center; top: 20px; position: relative;">Bedankt voor uw keuze.</div>';


		echo '<div class="col-sm-12">';
		echo '<h4>Bedankt voor uw keuze</h4>';
		if ($lijst == 31 || $lijst == 201) echo '<div class="fb-share-button" data-href="https://web.omroepgelderland.nl/muzieklijsten/fbshare.php?stemmer='.$last_id.'" data-layout="button" data-size="large" data-mobile-iframe="true"><a class="fb-xfbml-parse-ignore" target="_blank" href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fweb.omroepgelderland.nl%2Fmuzieklijsten%2Ffbshare.php%3Fstemmer%3D'.$last_id.'&amp;src=sdkpreparse">Deel mijn keuze op Facebook</a></div>';
		
	}
	
	session_unset(); 
	session_destroy();
	
} else if ($_POST["id"]) {
	echo '<div class="col-sm-12">';
	echo '<h4>Uw keuze</h4>';
	echo '<table class="table" id="keuzes">
	<thead>
	<tr>
		<th>Titel</th>
		<th>Artiest</th>
		<th>Toelichting</th>
	</tr>
	</thead>
	<tbody>';
	foreach ($_POST["id"] as $key => $value) {
		$value = (int)$value;
		
		$sql = "SELECT * FROM ".$prefix."muzieklijst_nummers WHERE id = ".$value;
		$result = $link->query($sql);
		
		while ($r = mysqli_fetch_array($result)) {
			echo "<tr>";
			echo "<td>".$r["titel"]."</td>";
			echo "<td>".$r["artiest"]."</td>";
			echo '<td class="remark"><input name="id_'.$r["id"].'" type="text" class="form-control"></td>';
			echo "</tr>";
		}
	
	}
	echo '</tbody></table>';
	echo '</div>';
	

}
