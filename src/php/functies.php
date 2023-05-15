<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

/**
 * Geef aan of dit de ontwikkelingsversie is.
 * @return bool Of het programma in ontwikkelingsmodus draait.
 */
function is_dev(): bool {
	return gethostname() === 'og-webdev1';
}

/**
 * Geeft de naam van de developer op wiens omgeving het project nu draait.
 * @throws Muzieklijsten_Exception Als het project niet op een ontwikkelingsomgeving draait.
 * @return string
 */
function get_developer(): string {
	if ( !is_dev() ) { throw new Muzieklijsten_Exception(); }
	$res = preg_match('~^/home/([^/]+)/~i', __DIR__, $m);
	if ( $res !== 1 ) { throw new Muzieklijsten_Exception(); }
	return $m[1];
}

/**
 * Geeft aan of het script handmatig (niet via cron o.i.d.) via de commandline is aangeroepen.
 * @return bool
 */
function is_cli(): bool {
	return php_sapi_name() === 'cli' && isset($_SERVER['TERM']);
}

/**
 * Vereist HTTP login voor beheerders
 */
function login(): void {
	session_start();
	if ( array_key_exists('is_ingelogd', $_SESSION) && $_SESSION['is_ingelogd'] ) {
		return;
	}
	if ( !isset($_SERVER['PHP_AUTH_USER']) ) {
		header('WWW-Authenticate: Basic realm="Inloggen"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Je moet inloggen om deze pagina te kunnen zien.';
		exit();
	}
	if (
		$_SERVER['PHP_AUTH_USER'] !== Config::get_instelling('php_auth', 'user')
		|| $_SERVER['PHP_AUTH_PW'] !== Config::get_instelling('php_auth', 'password') )
	{
		// header('WWW-Authenticate: Basic realm="Inloggen"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Verkeerd wachtwoord en/of gebruikersnaam. Ververs de pagina met F5 om het nog een keer te proberen.';
		session_destroy();
		throw new Muzieklijsten_Exception('Verkeerd wachtwoord en/of gebruikersnaam');
	}
	$_SESSION['is_ingelogd'] = true;
}

/**
 * Geeft alle muzieklijsten.
 * @return Lijst[]
 */
function get_muzieklijsten(): array {
	return DB::selectObjectLijst('SELECT id FROM lijsten ORDER BY naam', Lijst::class);
}

/**
 * Geeft alle nummers.
 * @return Nummer[]
 */
function get_nummers(): array {
	return DB::selectObjectLijst('SELECT id FROM nummers', Nummer::class);
}

/**
 * Anonimiseert een waarde door er een hash van te maken.
 * Lege strings en null worden onveranderd teruggegeven.
 * @param mixed $waarde Invoer
 * @return ?string $uitvoer
 */
function anonimiseer( $waarde ): ?string {
	if ( $waarde === null || $waarde === '' ) {
		return $waarde;
	} else {
		return hash('sha224', $waarde);
	}
}

/**
 * Overkoepelende error handler voor aanroep met exception_error_handler.
 * Maakt een echte PHP exception bij fouten.
 */
function exception_error_handler( $errno, $errstr, $errfile, $errline ) {
	switch ( $errno ) {
		case E_ERROR:
			throw new ErrorErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_WARNING:
			if ( strpos($errstr, 'No such file or directory') !== false ) {
				throw new PadBestaatNiet($errstr, 0, $errno, $errfile, $errline);
			}
			elseif ( strpos($errstr, 'Undefined array key') !== false ) {
				throw new IndexException($errstr, 0, $errno, $errfile, $errline);
			}
			elseif ( strpos($errstr, 'Undefined property: ') === 0 ) {
				throw new UndefinedPropertyException($errstr, 0, $errno, $errfile, $errline);
			}
			else {
				throw new WarningErrorException($errstr, 0, $errno, $errfile, $errline);
			}
		case E_PARSE:
			throw new ParseErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_NOTICE:
			if ( strpos($errstr, 'Undefined index: ') === 0 || strpos($errstr, 'Undefined offset: ') === 0 ) {
				throw new IndexException($errstr, 0, $errno, $errfile, $errline);
			} 
			elseif ( strpos($errstr, 'Trying to get property ') === 0 ) {
				throw new UndefinedPropertyException($errstr, 0, $errno, $errfile, $errline);
			}
			elseif ( strpos($errstr, 'Undefined property: ') === 0 ) {
				throw new UndefinedPropertyException($errstr, 0, $errno, $errfile, $errline);
			} else {
				throw new NoticeErrorException($errstr, 0, $errno, $errfile, $errline);
			}
		case E_CORE_ERROR:
			throw new CoreErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_CORE_WARNING:
			throw new CoreWarningErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_COMPILE_ERROR:
			throw new CompileErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_COMPILE_WARNING:
			throw new CompileWarningErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_USER_ERROR:
			throw new UserErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_USER_WARNING:
			throw new UserWarningErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_USER_NOTICE:
			throw new UserNoticeErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_STRICT:
			throw new StrictErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_RECOVERABLE_ERROR:
			throw new RecoverableErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_DEPRECATED:
			throw new DeprecatedErrorException($errstr, 0, $errno, $errfile, $errline);
		case E_USER_DEPRECATED:
			throw new UserDeprecatedErrorException($errstr, 0, $errno, $errfile, $errline);
		default:
			throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
	}
}

/**
 * Uit OLERead
 */
function GetInt4d( $data, $pos ) {
	$value = ord($data[$pos]) | (ord($data[$pos+1])  << 8) | (ord($data[$pos+2]) << 16) | (ord($data[$pos+3]) << 24);
	if($value >= 4294967294) $value = -2;
	return $value;
}

function is_captcha_ok( string $g_recaptcha_response ): bool {
	$recaptcha = Config::get_recaptcha();
	$resp = $recaptcha->verify($g_recaptcha_response, $_SERVER['REMOTE_ADDR']);
	return $resp->isSuccess();
}

/**
 * Checkt en format een postcode.
 * @param mixed $postcode Ruwe input.
 * @return ?string De geparste postcode of null als de invoer leeg is.
 */
function filter_postcode( $postcode ): ?string {
	if ( $postcode === false ) {
		throw new OngeldigeInvoer('Ongeldige postcode');
	}
	elseif ( $postcode === null || $postcode === '' ) {
		return null;
	}
	elseif ( preg_match('~([0-9]{4}).*([a-zA-Z]{2})~', $postcode, $m) === 1 ) {
		return sprintf(
			'%s %s',
			$m[1],
			strtoupper($m[2])
		);
	} else {
		throw new OngeldigeInvoer("Ongeldige postcode: \"{$postcode}\"");
	}
}

/**
 * Checkt en format een telefoonnummer.
 * Leestekens worden weggefilterd.
 * Lokale nummers worden internationaal gemaakt.
 * Nederlands nummers worden gecheckt op geldige prefix en lengte.
 * @param mixed $telefoonnummer Ruwe input.
 * @return ?string Het geparste telefoonnummer of null als de invoer leeg is.
 */
function filter_telefoonnummer( mixed $telefoonnummer ): ?string {
	if ( $telefoonnummer === false ) {
		throw new OngeldigeInvoer('Ongeldig telefoonnummer');
	}
	elseif ( $telefoonnummer === null || $telefoonnummer === '' ) {
		return null;
	}
	if ( strlen($telefoonnummer) < 4 ) {
		throw new OngeldigeInvoer("Ongeldig telefoonnummer: \"{$telefoonnummer}\"");
	}
	$telefoonnummer = preg_replace('~[^+0-9]~', '', $telefoonnummer);
    if ( substr($telefoonnummer, 0, 2) === '00' ) {
        // Handmatige uitbelcode
        $telefoonnummer = '+'.substr($telefoonnummer, 2);
    }
    if ( $telefoonnummer[0] === '0' ) {
        // Lokaal Nederlands nummer
        $telefoonnummer = '+31'.substr($telefoonnummer, 1);
    }
    if ( substr($telefoonnummer, 0, 4) === '+310' ) {
        // Onnodige nul na landcode
        $telefoonnummer = '+31'.substr($telefoonnummer, 4);
    }
    if ( substr($telefoonnummer, 0, 3) === '+31' ) {
        // Nederlands nummer
        if ( preg_match('~^\+31([1-5][0-9]|6[1-5]|68|7[0-9]|80|82|84|85|87|88|91)[0-9]{7}$~', $telefoonnummer) === 1 ) {
            return $telefoonnummer;
        } else {
            throw new OngeldigeInvoer("Ongeldig telefoonnummer: \"{$telefoonnummer}\"");
        }
    }
    if ( preg_match('~^\+[0-9]{8,15}$~', $telefoonnummer) === 1 ) {
            // Internationaal nummer
            return $telefoonnummer;
    } else {
        throw new OngeldigeInvoer("Ongeldig telefoonnummer: \"{$telefoonnummer}\"");
    }
}

/**
 * Verwijder stemmers die geen stemmen meer hebben.
 */
function verwijder_stemmers_zonder_stemmen(): void {
	$query = <<<EOT
		DELETE
		FROM stemmers
		WHERE id NOT IN (
			SELECT stemmer_id
			FROM stemmen
		)
	EOT;
	DB::query($query);
}

function filter_van_tot( string $waarde ): ?\DateTime {
	$str = filter_var($waarde);
    if ( isset($str) ) {
        return \DateTime::createFromFormat('d-m-Y', $str);
    } else {
		return null;
	}
}

/**
 * Verstuur een mail
 * @param string[]|string $aan Lijst met ontvangers
 * @param string[]|string $cc Lijst met ontvangers
 * @param string $van Adres van afzender
 * @param string $onderwerp Onderwerp
 * @param string $tekst_bericht Het bericht in plaintext
 * @param ?string $html_bericht Het bericht in HTML (optioneel)
 * @param ?string $bijlage Pad naar mee te sturen bijlage (optioneel, alleen pdf)
 */
function stuur_mail(
	$aan,
	$cc,
	string $van,
	string $onderwerp,
	string $tekst_bericht,
	?string $html_bericht = null,
	?string $bijlage = null
): void {
	if ( !is_array($aan) ) {
		$aan = [$aan];
	}
	if ( !is_array($cc) ) {
		$cc = [$cc];
	}
	$ontvangers = array_merge($aan, $cc); 
	$crlf = "\n";
	$headers = [
		'From' => $van,
		'To' => implode(',', $aan),
		'Cc' => implode(',', $cc),
		'Subject' => $onderwerp
	];
	$mime = new \Mail_mime($crlf);
	$mime->setTXTBody($tekst_bericht);
	if ( isset($html_bericht) ) {
		$mime->setHTMLBody($html_bericht);
	}

	if ( isset($bijlage) ) {
		$mime->addAttachment($bijlage, 'application/pdf');
	}

	$mime_params = [
		'text_encoding' => '7bit',
		'text_charset' => 'UTF-8',
		'html_charset' => 'UTF-8',
		'head_charset' => 'UTF-8'
	];
	$body = $mime->get($mime_params);
	$headers = $mime->headers($headers);
	
	$params = [
		'sendmail_path' => Config::get_instelling('mail', 'sendmail_path')
	];
	$mail_obj = \Mail::factory('sendmail', $params);
	set_error_handler('\muzieklijsten\exception_error_handler', E_ALL & ~E_DEPRECATED);
	error_reporting(E_ALL & ~E_DEPRECATED);
	$mail_obj->send($ontvangers, $headers, $body);
	error_reporting(E_ALL);
	set_error_handler('\muzieklijsten\exception_error_handler', E_ALL);
}

/**
 * Plakt paden aan elkaar met slashes.
 * @param string $paths,... Meerdere paden die aan elkaar geplakt worden. Het
 * aantal parameters is variabel.
 * @return string Het resulterende pad.
 */
function path_join( ...$paths ): string {
	foreach ( $paths as $i => $arg ) {
		if ( $i == 0 ) {
			// eerste parameter
			$path = rtrim($arg, '/') . '/';
		} elseif ( $i == count($paths) - 1 ) {
			// laatste parameter
			$path .= ltrim($arg, '/');
		} else {
			$path .= trim($arg, '/') . '/';
		}
	}
	return $path;
}

/**
 * Geeft het pad naar het eerst gestartte script, los van de class van waaruit deze functie wordt aangeroepen.
 * @return string
 */
function get_hoofdscript_pad(): string {
	return get_included_files()[0];
}

/**
 * Geeft een error als het IP van de gebruiker op de blacklist staat.
 * @param string $ip
 * @throws BlacklistException
 */
function check_ip_blacklist( string $ip ): void {
	if ( DB::recordBestaat("SELECT ip FROM blacklist WHERE ip = \"{$ip}\"") ) {
		throw new BlacklistException();
	}
}

/**
 * @return Veld[]
 */
function get_velden(): array {
	$velden = [];
	$query = 'SELECT * FROM velden ORDER BY id';
	foreach ( DB::query($query) as $entry ) {
		$velden[] = new Veld($entry['id'], $entry);
	}
	return $velden;
}
