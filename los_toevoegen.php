<?php

require_once __DIR__.'/include/include.php';

function verboden( $msg ) {
    header('WWW-Authenticate: Basic realm="Inloggen"');
    header('HTTP/1.0 401 Unauthorized');
    echo $msg;
    exit();
}

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

function logincheck( $loginnaam, $wachtwoord ) {
	if ( $loginnaam != 'gld' || $wachtwoord != 'muziek=fijn' ) {
		throw new Muzieklijsten_Exception('De logingegevens zijn incorrect');
	}
}

function toevoegen( $db, $nummers, $lijsten ) {
	$json['toegevoegd'] = 0;
	$json['dubbel'] = 0;
	foreach( $nummers as $nummer ) {
		$artiest = $nummer['artiest'];
		$titel = $nummer['titel'];
		if ( $titel != '' && $artiest != '' ) {
			$zoekartiest = $db->real_escape_string(strtolower(str_replace(' ', '', $artiest)));
			$zoektitel = $db->real_escape_string(strtolower(str_replace(' ', '', $titel)));
			$sql = sprintf(
				'SELECT id FROM muzieklijst_nummers WHERE LOWER(REPLACE(artiest, " ", "")) = "%s" AND LOWER(REPLACE(titel, " ", "")) = "%s"',
				$zoekartiest,
				$zoektitel
			);
			$res = $db->query($sql);
			if ( $res === false ) {
				throw new SQLException(sprintf(
					'SQL query mislukt: "%s"',
					$sql
				));
			}
			if ( $res->num_rows > 0 ) {
				$json['dubbel']++;
			} else {
				$sql = sprintf(
					'INSERT INTO muzieklijst_nummers (titel, artiest) VALUES ("%s", "%s")',
					$db->real_escape_string($titel),
					$db->real_escape_string($artiest)
				);
				$res = $db->query($sql);
				if ( $res === false ) {
					throw new SQLException(sprintf(
						'SQL query mislukt: "%s"',
						$sql
					));
				}
				$json['toegevoegd']++;
				$nummer_id = $db->insert_id;
				foreach( $lijsten as $lijst ) {
					$sql = sprintf(
						'INSERT INTO muzieklijst_nummers_lijst (nummer_id, lijst_id) VALUES (%d, %d)',
						$nummer_id,
						$lijst
					);
					$res = $db->query($sql);
					if ( $res === false ) {
						throw new SQLException(sprintf(
							'SQL query mislukt: "%s"',
							$sql
						));
					}
				}
			}
		}
	}
	return $json;
}

function getLijsten( $db ) {
	$res = $db->query('SELECT id, naam FROM muzieklijst_lijsten ORDER BY naam');
	if ( $res === false ) {
		throw new SQLException(sprintf(
			'SQL query mislukt: "%s"',
			$sql
		));
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
	$db->set_charset('utf8');
	// Omzetter voor data uit Angular
	$params = json_decode(file_get_contents('php://input'),true);
	
	switch ( $params['query'] ) {
		case 'toevoegen':
			logincheck($params['loginnaam'], $params['wachtwoord']);
			$json = toevoegen($db, $params['nummers'], $params['lijsten']);
			break;
		case 'getLijsten':
			$json = getLijsten($db);
			break;
		default:
			throw new SQLException(sprintf('Query "%s" is onbekend', $params['query']));
	}
	
	header('Content-Type: application/json');
	echo json_encode($json);
} catch ( Exception $e ) {
	exception_serverError($e);
}
