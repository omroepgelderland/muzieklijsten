<?php

/**
 * Geef aan of dit de ontwikkelingsversie is.
 * @return boolean Of het programma in ontwikkelingsmodus draait.
 */
function is_dev() {
	return ( gethostname() == 'og-webdev1.omroep.local' );
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
