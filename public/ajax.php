<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

use stdClass;

require_once __DIR__.'/../vendor/autoload.php';

/**
 * Lijst verwijderen vanuit de beheerdersinterface.
 * @param \stdClass $request HTTP-request.
 * @throws GeenLijstException
 */
function verwijder_lijst( \stdClass $request ) {
    login();
	$lijst = Lijst::maak_uit_request($request);
	$lijst->verwijderen();
}

/**
 * Haalt metadata van een lijst uit het request.
 * @param \stdClass $request HTTP-request.
 */
function filter_lijst_metadata( \stdClass $request ): array {
	$naam = trim(filter_var($request->naam));
	$is_actief = isset($request->{'is-actief'});
	$minkeuzes = filter_var($request->minkeuzes, FILTER_VALIDATE_INT);
	$maxkeuzes = filter_var($request->maxkeuzes, FILTER_VALIDATE_INT);
	$vrijekeuzes = filter_var($request->vrijekeuzes, FILTER_VALIDATE_INT);
	$stemmen_per_ip = filter_var($request->{'stemmen-per-ip'}, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
	$artiest_eenmalig = isset($request->{'artiest-eenmalig'});
	$recaptcha = isset($request->recaptcha);
	$emails = explode(',', filter_var($request->email));
	$emails_geparsed = [];
	foreach ( $emails as $email ) {
		$email = trim(strtolower($email));
		if ( $email === '' ) {
			continue;
		}
		$email_geparsed = filter_var($email, FILTER_VALIDATE_EMAIL);
		if ( $email_geparsed === false ) {
			throw new GebruikersException("Ongeldig e-mailadres: \"{$email}\"");
		}
		$emails_geparsed[] = $email_geparsed;
	}
	$emails_str = implode(',', $emails_geparsed);
	$bedankt_tekst = trim(filter_var($request->{'bedankt-tekst'}));

	if ( $naam === '' ) {
		throw new GebruikersException('Geef een naam aan de lijst.');
	}
	if ( $minkeuzes === false ) {
		throw new GebruikersException('Het minimaal aantal keuzes moet meer dan één zijn.');
	}
	if ( $maxkeuzes === false ) {
		throw new GebruikersException('Het maximaal aantal keuzes moet meer dan één zijn.');
	}
	if ( $maxkeuzes < $minkeuzes ) {
		throw new GebruikersException('Het maximum aantal keuzes kan niet lager zijn dan het minimum.');
	}
	if ( $vrijekeuzes === false ) {
		$vrijekeuzes = 0;
	}
	if ( $stemmen_per_ip < 1 ) {
		$stemmen_per_ip = null;
	}
	return [
		'naam' => $naam,
		'actief'=> $is_actief,
		'minkeuzes' => $minkeuzes,
		'maxkeuzes' => $maxkeuzes,
		'vrijekeuzes' => $vrijekeuzes,
		'stemmen_per_ip' => $stemmen_per_ip,
		'artiest_eenmalig' => $artiest_eenmalig,
		'recaptcha' => $recaptcha,
		'email' => $emails_str,
		'bedankt_tekst' => $bedankt_tekst
	];
}

function set_lijst_velden( Lijst $lijst, \stdClass $input_velden ) {
	$alle_velden = get_velden();
	foreach ( $alle_velden as $veld ) {
		try {
			$id = $veld->get_id();
			$input_veld = $input_velden->$id;
			$tonen = isset($input_veld->tonen);
			$verplicht = isset($input_veld->verplicht);
			if ( $tonen ) {
				$lijst->set_veld($veld, $verplicht);
			} else {
				$lijst->remove_veld($veld);
			}
		} catch ( UndefinedPropertyException ) {
			$lijst->remove_veld($veld);
		}
	}
}

/**
 * Bestaande lijst opslaan in de beheerdersinterface.
 * @param \stdClass $request HTTP-request.
 * @throws GeenLijstException
 */
function lijst_opslaan( \stdClass $request ): void {
    login();
	$lijst = Lijst::maak_uit_request($request);
	$data = filter_lijst_metadata($request);
	DB::updateMulti('lijsten', $data, "id = {$lijst->get_id()}");
	try {
		$velden = $request->velden;
	} catch ( UndefinedPropertyException ) {
		$velden = new \stdClass();
	}
	set_lijst_velden($lijst, $velden);
}

/**
 * Nieuwe lijst maken in de beheerdersinterface.
 * @param \stdClass $request HTTP-request.
 * @return int ID van de nieuwe lijst.
 */
function lijst_maken( \stdClass $request ): int {
    login();
	$data = filter_lijst_metadata($request);
	$lijst = new Lijst(DB::insertMulti('lijsten', $data));
	try {
		$velden = $request->velden;
	} catch ( UndefinedPropertyException ) {
		$velden = new \stdClass();
	}
	set_lijst_velden($lijst, $velden);
	return $lijst->get_id();
}

/**
 * Losse nummers toevoegen aan de database.
 * @param \stdClass $request HTTP-request.
 * @return array
 */
function losse_nummers_toevoegen( \stdClass $request ): array {
    login();
	$request->nummers ??= [];
	$request->lijsten ??= [];
    $json = [];
	$json['toegevoegd'] = 0;
	$json['dubbel'] = 0;
	foreach( $request->nummers as $nummer ) {
		$artiest = trim($nummer->artiest);
		$titel = trim($nummer->titel);
		if ( $titel !== '' && $artiest !== '' ) {
			$zoekartiest = DB::escape_string(strtolower(str_replace(' ', '', $artiest)));
			$zoektitel = DB::escape_string(strtolower(str_replace(' ', '', $titel)));
            $sql = <<<EOT
                SELECT id
                FROM nummers
                WHERE
                    LOWER(REPLACE(artiest, " ", "")) = "{$zoekartiest}"
                    AND LOWER(REPLACE(titel, " ", "")) = "{$zoektitel}"
            EOT;
			$res = DB::query($sql);
			if ( $res->num_rows > 0 ) {
				$json['dubbel']++;
			} else {
				$json['toegevoegd']++;
                $nummer_id = DB::insertMulti('nummers', [
                    'titel' => $titel,
                    'artiest' => $artiest
                ]);
				foreach( $request->lijsten as $lijst ) {
                    DB::insertMulti('lijsten_nummers', [
                        'nummer_id' => $nummer_id,
                        'lijst_id' => $lijst
                    ]);
				}
			}
		}
	}
	return $json;
}

/**
 * Geeft alle lijsten voor het toevoegen van losse nummers.
 * @return array
 */
function ajax_get_lijsten(): array {
    $respons = [];
    foreach ( get_muzieklijsten() as $lijst ) {
        $respons[] = [
            'id' => $lijst->get_id(),
            'naam' => $lijst->get_naam()
        ];
    }
	return $respons;
}

/**
 * Instellen dat een stem is behandeld door de redactie.
 * @param \stdClass $request HTTP-request.
 * @throws GeenLijstException
 */
function stem_set_behandeld( \stdClass $request ): void {
    login();
	$stem = Stem::maak_uit_request($request);
	$waarde = filter_var($request->waarde, FILTER_VALIDATE_BOOL);
	$stem->set_behandeld($waarde);
}

/**
 * Verwijderen van een stem in de resultateninterface.
 * @param \stdClass $request HTTP-request.
 * @throws GeenLijstException
 */
function verwijder_stem( \stdClass $request ): void {
    login();
    $lijst = Lijst::maak_uit_request($request);
	$stem = new Stem(
		Nummer::maak_uit_request($request),
		$lijst,
		Stemmer::maak_uit_request($request)
	);
	$stem->verwijderen();
	verwijder_ongekoppelde_vrije_keuze_nummers();
}

/**
 * Verwijderen van een nummer in de resultateninterface.
 * (wordt momenteel niet gebruikt)
 * @param \stdClass $request HTTP-request.
 * @throws GeenLijstException
 */
function verwijder_nummer( \stdClass $request ): void {
    login();
    $lijst = Lijst::maak_uit_request($request);
	$lijst->verwijder_nummer(Nummer::maak_uit_request($request));
}

/**
 * Geeft het totaal aantal stemmers op een lijst.
 * @param \stdClass $request HTTP-request.
 * @throws GeenLijstException
 */
function get_totaal_aantal_stemmers( \stdClass $request ): int {
    login();
    $lijst = Lijst::maak_uit_request($request);
	try {
    	$van = filter_van_tot($request->van);
	} catch ( UndefinedPropertyException ) {
		$van = null;
	}
	try {
    	$tot = filter_van_tot($request->tot);
	} catch ( UndefinedPropertyException ) {
		$tot = null;
	}
    return count($lijst->get_stemmers($van, $tot));
}

/**
 * Verwerk een stem van een bezoeker.
 * @param \stdClass $request HTTP-request.
 * @return string HTML-respons.
 * @throws GeenLijstException
 */
function stem( \stdClass $request ): string {
    $lijst = Lijst::maak_uit_request($request);
	if ( $lijst->heeft_gebruik_recaptcha() && !is_captcha_ok($request->{'g-recaptcha-response'}) ) {
		throw new GebruikersException('Captcha verkeerd.');
	}
	if ( $lijst->is_max_stemmen_per_ip_bereikt($lijst) ) {
		return 'Het maximum aantal stemmen voor dit IP-adres is bereikt.';
	}
	try {
		$stemmer = verwerk_stem($lijst, $request);
		$is_blacklist = false;
	} catch ( BlacklistException ) {
		$is_blacklist = true;
	}
	$bedankt_tekst = htmlspecialchars($lijst->get_bedankt_tekst());
	$html = "<h4>{$bedankt_tekst}</h4>";
	if ( !$is_blacklist && ( $lijst->get_id() == 31 || $lijst->get_id() == 201 ) ) {
		if ( is_dev() ) {
			$fbshare_url = sprintf(
				'https://webdev.gld.nl/%s/muzieklijsten/fbshare.php?stemmer=%d',
				get_developer(),
				$stemmer->get_id()
			);
			$fb_url = 'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fwebdev.gld.nl%2F'.get_developer().'%2Fmuzieklijsten%2Ffbshare.php%3Fstemmer%3D'.$stemmer->get_id().'&amp;src=sdkpreparse';
		} else {
			$fbshare_url = 'https://web.omroepgelderland.nl/muzieklijsten/fbshare.php?stemmer='.$stemmer->get_id();
			$fb_url = 'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fweb.omroepgelderland.nl%2Fmuzieklijsten%2Ffbshare.php%3Fstemmer%3D'.$stemmer->get_id().'&amp;src=sdkpreparse';
		}
		$html .= <<<EOT
			<div class="fb-share-button" data-href="{$fbshare_url}" data-layout="button" data-size="large" data-mobile-iframe="true">
				<a class="fb-xfbml-parse-ignore" target="_blank" href="{$fb_url}">Deel mijn keuze op Facebook</a>
			</div>
		EOT;
	}
	return $html;
}

function verwerk_stem( Lijst $lijst, \stdClass $request ): Stemmer {
	check_ip_blacklist($_SERVER['REMOTE_ADDR']);
	$stemmer = Stemmer::maak(
		$_SERVER['REMOTE_ADDR']
	);
    
    foreach( $request->nummers as $input_nummer ) {
		$nummer = new Nummer(filter_var($input_nummer->id, FILTER_VALIDATE_INT));
		$toelichting = filter_var($input_nummer->toelichting);
        $stemmer->add_stem($nummer, $lijst, $toelichting);
    }
	// Invoer van (optionele) vrije keuzes
	try {
		$vrijekeuzes = $request->vrijekeuzes;
	} catch ( UndefinedPropertyException ) {
		$vrijekeuzes = [];
	}
	foreach ( $vrijekeuzes as $vrijekeus_invoer ) {
		try {
			$nummer = Nummer::vrijekeuze_toevoegen(
				$vrijekeus_invoer->artiest,
				$vrijekeus_invoer->titel
			);
			$stemmer->add_stem($nummer, $lijst, $vrijekeus_invoer->toelichting, true);
		} catch ( LegeVrijeKeuze ) {}
	}
	
	// Invoer van velden
	foreach ( $lijst->get_velden() as $veld ) {
		try {
			$id = $veld->get_id();
			$waarde = $request->velden->$id;
		} catch ( UndefinedPropertyException ) {
			$waarde = null;
		}
		if ( $veld->get_type() === 'checkbox' ) {
			$waarde = filter_var($waarde, FILTER_VALIDATE_BOOL);
		}
		elseif ( $veld->get_type() === 'date' ) {
			$waarde = (new \DateTime($waarde))->format('Y-m-d');
		}
		elseif ( $veld->get_type() === 'email' ) {
			$waarde = filter_var($waarde, FILTER_SANITIZE_EMAIL);
			if ( $waarde !== null ) {
				$waarde = strtolower($waarde);
			}
		}
		elseif ( $veld->get_type() === 'month' || $veld->get_type() === 'number' || $veld->get_type() === 'week' ) {
			$waarde = filter_var($waarde, FILTER_VALIDATE_INT);
		}
		elseif ( $veld->get_type() === 'postcode' ) {
			$waarde = filter_postcode($waarde);
		}
		elseif ( $veld->get_type() === 'tel' ) {
			$waarde = filter_telefoonnummer($waarde);
		}
		else {
			$waarde = filter_var($waarde);
		}
		if ( $waarde !== false && $waarde !== null ) {
			$veld->add_waarde($stemmer, $waarde);
		} elseif ( $veld->is_verplicht() ) {
			throw new GebruikersException(sprintf(
				'Veld %s is verplicht voor de lijst %s',
				$veld->get_label(),
				$lijst->get_naam()
			));
		}
	}

	$stemmer->mail_redactie($lijst);
	return $stemmer;
}

/**
 * Voegt een nummer toe aan een stemlijst.
 * @param \stdClass $request HTTP-request.
 * @throws GeenLijstException
 */
function lijst_nummer_toevoegen( \stdClass $request ): void {
    login();
    DB::disableAutocommit();
    $lijst = Lijst::maak_uit_request($request);
    $lijst->nummer_toevoegen(Nummer::maak_uit_request($request));
    DB::commit();
}

/**
 * Haalt een nummer weg uit een stemlijst.
 * @param \stdClass $request HTTP-request.
 * @throws GeenLijstException
 */
function lijst_nummer_verwijderen( \stdClass $request ): void {
    login();
    DB::disableAutocommit();
    $lijst = Lijst::maak_uit_request($request);
    $lijst->verwijder_nummer(Nummer::maak_uit_request($request));
    DB::commit();
}

function get_geselecteerde_nummers( \stdClass $request ): array {
    try {
        $lijst = Lijst::maak_uit_request($request);
        $nummers = $lijst->get_nummers_sorteer_titels();
    } catch ( Muzieklijsten_Exception $e ) {
        $nummers = [];
    }
    $respons = [];
    foreach ( $nummers as $nummer ) {
		$respons[] = [
			'id' => $nummer->get_id(),
			'titel' => $nummer->get_titel(),
			'artiest' => $nummer->get_artiest(),
			'jaar' => $nummer->get_jaar()
		];
    }
	return $respons;
}

/**
 * @param \stdClass $request HTTP-request.
 * @return array{
 * draw: int,
 * recordsTotal: int,
 * recordsFiltered: int,
 * data: array<string[]>
 * }
 * @throws GeenLijstException
 */
function vul_datatables( \stdClass $request ): array {
    $ssp = new SSP(
        $request,
        [
            [
                'db' => 'id',
                'dt' => 0
            ], [
                'db' => 'titel',
                'dt' => 1
            ], [
                'db' => 'artiest',
                'dt' => 2
            ], [
                'db' => 'jaar',
                'dt' => 3
            ]
        ]
    );
    $res = $ssp->simple();
	return $res;
}

/**
 * Geeft data over de stemlijst voor de stempagina.
 * @param \stdClass $request HTTP-request.
 * @return array
 * @throws GeenLijstException
 */
function get_stemlijst_frontend_data( \stdClass $request ): array {
	try {
		$lijst = Lijst::maak_uit_request($request);
		$lijst->get_naam();	// Forceer dat de lijst in de database wordt geopend.
	} catch ( SQLException ) {
		throw new GebruikersException('Ongeldige lijst');
	}

	$velden = [];
	foreach ( $lijst->get_velden() as $veld ) {
		$velddata = [
			'id' => $veld->get_id(),
			'label' => $veld->get_label(),
			'leeg_feedback' => $veld->get_leeg_feedback(),
			'type' => $veld->get_type(),
			'verplicht' => $veld->is_verplicht()
		];
		try {
			$velddata['max'] = $veld->get_max();
		} catch ( ObjectEigenschapOntbreekt ) {}
		try {
			$velddata['maxlength'] = $veld->get_maxlength();
		} catch ( ObjectEigenschapOntbreekt ) {}
		try {
			$velddata['min'] = $veld->get_min();
		} catch ( ObjectEigenschapOntbreekt ) {}
		try {
			$velddata['minlength'] = $veld->get_minlength();
		} catch ( ObjectEigenschapOntbreekt ) {}
		try {
			$velddata['placeholder'] = $veld->get_placeholder();
		} catch ( ObjectEigenschapOntbreekt ) {}
		$velden[] = $velddata;
	}

	return [
		'minkeuzes' => $lijst->get_minkeuzes(),
		'maxkeuzes' => $lijst->get_maxkeuzes(),
		'vrijekeuzes' => $lijst->get_vrijekeuzes(),
		'is_artiest_eenmalig' => $lijst->is_artiest_eenmalig(),
		'organisatie' => Config::get_instelling('organisatie'),
		'lijst_naam' => $lijst->get_naam(),
		'heeft_gebruik_recaptcha' => $lijst->heeft_gebruik_recaptcha(),
		'is_actief' => $lijst->is_actief(),
		'is_max_stemmen_per_ip_bereikt' => $lijst->is_max_stemmen_per_ip_bereikt(),
		'velden' => $velden,
		'recaptcha_sitekey' => Config::get_instelling('recaptcha', 'sitekey'),
		'privacy_url' => Config::get_instelling('privacy_url')
	];
}

function get_resultaten_labels( \stdClass $request ): array {
	login();
	try {
		$lijst = Lijst::maak_uit_request($request);
	} catch ( SQLException ) {
		throw new GebruikersException('Ongeldige lijst');
	}
	$respons = [];
	foreach ( $lijst->get_velden() as $veld ) {
		$respons[] = $veld->get_label();
	}
	return $respons;
}

function get_resultaten( \stdClass $request ): array {
	login();
	try {
		$lijst = Lijst::maak_uit_request($request);
	} catch ( SQLException ) {
		throw new GebruikersException('Ongeldige lijst');
	}
	return $lijst->get_resultaten();
}

function get_lijst_metadata( \stdClass $request ): array {
	login();
	try {
		$lijst = Lijst::maak_uit_request($request);
	} catch ( SQLException ) {
		throw new GebruikersException('Ongeldige lijst');
	}
	$nummer_ids = [];
	foreach ( $lijst->get_nummers() as $nummer ) {
		$nummer_ids[] = (string)$nummer->get_id();
	}
	return [
		'naam' => $lijst->get_naam(),
		'nummer_ids' => $nummer_ids,
		'iframe_url' => sprintf(
			'%s?lijst=%d',
			Config::get_instelling('root_url'),
			$lijst->get_id()
		)
	];
}

function get_metadata(): array {
	login();
	$lijsten =[];
	foreach ( get_muzieklijsten() as $lijst ) {
		$lijsten[] = [
			'id' => $lijst->get_id(),
			'naam' => $lijst->get_naam()
		];
	}
	return [
		'organisatie' => Config::get_instelling('organisatie'),
		'lijsten' => $lijsten,
		'nimbus_url' => Config::get_instelling('nimbus_url'),
		'totaal_aantal_nummers' => DB::selectSingle('SELECT COUNT(*) FROM nummers')
	];
}

function get_alle_velden(): array {
	$respons = [];
	foreach ( get_velden() as $veld ) {
		$respons[] = [
			'id' => $veld->get_id(),
			'tonen' => false,
			'label' => $veld->get_label(),
			'verplicht' => false
		];
	}
	return $respons;
}

function get_beheer_lijstdata( \stdClass $request ): array {
	login();
	try {
		$lijst = Lijst::maak_uit_request($request);
	} catch ( SQLException ) {
		throw new GebruikersException('Ongeldige lijst');
	}
	return [
		'naam' => $lijst->get_naam(),
		'is_actief' => $lijst->is_actief(),
		'minkeuzes' => $lijst->get_minkeuzes(),
		'maxkeuzes' => $lijst->get_maxkeuzes(),
		'vrijekeuzes' => $lijst->get_vrijekeuzes(),
		'stemmen_per_ip' => $lijst->get_max_stemmen_per_ip(),
		'artiest_eenmalig' => $lijst->is_artiest_eenmalig(),
		'recaptcha' => $lijst->heeft_gebruik_recaptcha(),
		'email' => implode(',', $lijst->get_notificatie_email_adressen()),
		'bedankt_tekst' => $lijst->get_bedankt_tekst(),
		'velden' => $lijst->get_alle_velden_data()
	];
}

try {
    DB::disableAutocommit();
	if ( $_SERVER['CONTENT_TYPE'] === 'application/json' ) {
		$request = json_decode(file_get_contents('php://input'));
	} else {
		$request = json_decode(json_encode($_POST));
	}
    $functie = filter_var($request->functie);
    $respons = [];
    $data = null;
    switch ($functie) {
		case 'get_stemlijst_frontend_data':
			$data = get_stemlijst_frontend_data($request);
			break;
        case 'verwijder_lijst':
            verwijder_lijst($request);
            break;
        case 'lijst_opslaan':
            lijst_opslaan($request);
            break;
		case 'lijst_maken':
			$data = lijst_maken($request);
			break;
        case 'losse_nummers_toevoegen':
            $data = losse_nummers_toevoegen($request);
            break;
        case 'get_lijsten':
            $data = ajax_get_lijsten();
            break;
        case 'stem_set_behandeld':
            stem_set_behandeld($request);
            break;
        case 'verwijder_stem':
            verwijder_stem($request);
            break;
        case 'verwijder_nummer':
            verwijder_nummer($request);
            break;
        case 'get_totaal_aantal_stemmers':
            $data = get_totaal_aantal_stemmers($request);
            break;
        case 'stem':
            $data = stem($request);
            break;
        case 'lijst_nummer_toevoegen':
            lijst_nummer_toevoegen($request);
            break;
        case 'lijst_nummer_verwijderen':
            lijst_nummer_verwijderen($request);
            break;
        case 'vul_datatables':
            $data = vul_datatables($request);
            break;
        case 'get_geselecteerde_nummers':
            $data = get_geselecteerde_nummers($request);
            break;
		case 'login':
			login();
			break;
		case 'get_resultaten_labels':
			$data = get_resultaten_labels($request);
			break;
		case 'get_resultaten':
			$data = get_resultaten($request);
			break;
		case 'get_lijst_metadata':
			$data = get_lijst_metadata($request);
			break;
		case 'get_metadata':
			$data = get_metadata();
			break;
		case 'get_alle_velden':
			$data = get_alle_velden();
			break;
		case 'get_beheer_lijstdata':
			$data = get_beheer_lijstdata($request);
			break;
        default:
            throw new Muzieklijsten_Exception("Onbekende ajax-functie: \"{$functie}\"");
    }
    DB::commit();
    $respons['data'] = $data;
    $respons['error'] = false;
} catch ( GebruikersException $e ) {
    $respons['error'] = true;
    $respons['errordata'] = $e->getMessage();
} catch ( \Throwable $e ) {
    Log::err($e);
    $respons['error'] = true;
    $respons['errordata'] = is_dev()
    ? $e->getMessage()
    : 'fout';
}
header('Content-Type: application/json');
echo json_encode($respons);
