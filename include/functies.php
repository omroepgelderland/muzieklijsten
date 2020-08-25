<?php

/**
 * Geef aan of dit de ontwikkelingsversie is.
 * @return boolean Of het programma in ontwikkelingsmodus draait.
 */
function is_dev() {
	return ( gethostname() == 'og-webdev1.omroep.local' );
}

/**
 * Geeft de naam van de developer op wiens omgeving het project nu draait.
 * @throws Muzieklijsten_Exception Als het project niet op een ontwikkelingsomgeving draait.
 * @return string
 */
function get_developer() {
	if ( !is_dev() ) { throw new Muzieklijsten_Exception(); }
	$res = preg_match('~^/home/([^/]+)/~i', __DIR__, $m);
	if ( $res !== 1 ) { throw new Muzieklijsten_Exception(); }
	return $m[1];
}

/**
 * Vereist HTTP login voor beheerders
 */
function login() {
	if ( is_dev() ) {
		return;
	}
	if ( !isset($_SERVER['PHP_AUTH_USER']) ) {
		header('WWW-Authenticate: Basic realm="Inloggen"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Je moet inloggen om deze pagina te kunnen zien.';
		exit();
	}
	if ( $_SERVER['PHP_AUTH_USER'] != 'gld' || $_SERVER['PHP_AUTH_PW'] != 'muziek=fijn' ) {
		throw new Muzieklijsten_Exception('Verkeerd wachtwoord en/of gebruikersnaam');
	}
}

/**
 * Geeft alle muzieklijsten.
 * @return Muzieklijst[]
 */
function get_muzieklijsten() {
	return Muzieklijsten_Database::selectObjectLijst('SELECT id FROM muzieklijst_lijsten', Muzieklijst::class);
}

/**
 * Geeft alle nummers.
 * @return Nummer[]
 */
function get_nummers() {
	return Muzieklijsten_Database::selectObjectLijst('SELECT id FROM muzieklijst_nummers', Nummer::class);
}
