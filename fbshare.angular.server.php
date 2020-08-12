<?php

require_once __DIR__.'/include/include.php';

function fatal_error_handler() {
	$last_error = error_get_last();
	if ( $last_error === null ) {
		return;
	}
	$type = $last_error['type'];
	$message = $last_error['message'];
	$file = $last_error['file'];
	$line = $last_error['line'];
	if ( $type == E_ERROR ) {
		$type = 'Fatal run-time error';
	} elseif ( $type == E_PARSE ) {
		$type = 'Parse error';
	} elseif ( $type == E_CORE_ERROR ) {
		$type = 'Core fatal run-time error';
	} elseif ( $type == E_COMPILE_ERROR ) {
		$type = 'Fatal compile-time error';
	} elseif ( $type == E_USER_ERROR ) {
		$type = 'User-generated error';
	} else {
		return;
	}
	serverError(sprintf(
		'%s: %s in %s on line %d',
		$type,
		$message,
		$file,
		$line
	));
}

function exception_serverError( Exception $e ) {
	if ( is_dev() ) {
		echo $e;
	} else {
		serverError([
			'exception' => get_class($e),
			'message' => $e->getMessage(),
			'code' => $e->getCode()
		]);
	}
}

function serverError( $json ) {
	header($_SERVER['SERVER_PROTOCOL'].' 500 '.json_encode($json), True, 500);
	exit(1);
}

function getNummers( $db, $stemmer_id ) {
	$sql = sprintf(
		'SELECT n.artiest, n.titel FROM muzieklijst_nummers n JOIN muzieklijst_stemmen sn ON sn.nummer_id = n.id JOIN muzieklijst_stemmers s ON s.id = %d AND s.id = sn.stemmer_id ORDER BY sn.id',
		$stemmer_id
	);
	$res = $db->query($sql);
	if ( $res === false ) {
		throw new SQLException('Query mislukt');
	}
	$json = [];
	foreach( $res as $r ) {
		$json[] = $r;
	}
	return $json;
}

register_shutdown_function('fatal_error_handler');
if ( ! is_dev() ) {
	ini_set('display_errors', 'off');
}
try {
	
	$db = Muzieklijsten_Database::getDB();
	if ( $db === false ) {
		throw new SQLException('Database verbinding mislukt');
	}
	// Omzetter voor data uit Angular
	$params = json_decode(file_get_contents('php://input'),true);
	
	switch ( $params['query'] ) {
		case 'getNummers':
			$json = getNummers($db, $params['stemmer_id']);
			break;
		default:
			throw new Muzieklijsten_Exception(sprintf('Query "%s" is onbekend', $params['query']));
	}
	
	header('Content-Type: application/json');
	echo json_encode($json);
} catch ( Exception $e ) {
	exception_serverError($e);
}
