<?php

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

/**
 * Lijst verwijderen vanuit de beheerdersinterface.
 */
function verwijder_lijst() {
    login();
	$lijst = Lijst::maak_uit_request();
	$lijst->verwijderen();
}

/**
 * Haalt metadata van een lijst uit het request.
 */
function filter_lijst_metadata(): array {
	$naam = trim(filter_input(INPUT_POST, 'naam'));
	$is_actief = filter_input(INPUT_POST, 'is-actief', FILTER_VALIDATE_BOOL);
	$minkeuzes = filter_input(INPUT_POST, 'minkeuzes', FILTER_VALIDATE_INT);
	$maxkeuzes = filter_input(INPUT_POST, 'maxkeuzes', FILTER_VALIDATE_INT);
	$stemmen_per_ip = filter_input(INPUT_POST, 'stemmen-per-ip', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
	$artiest_eenmalig = filter_input(INPUT_POST, 'artiest-eenmalig', FILTER_VALIDATE_BOOL);
	$recaptcha = filter_input(INPUT_POST, 'recaptcha', FILTER_VALIDATE_BOOL) ?? false;
	$emails = explode(',', filter_input(INPUT_POST, 'email'));
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
	$bedankt_tekst = trim(filter_input(INPUT_POST, 'bedankt-tekst'));

	if ( $naam === '' ) {
		throw new GebruikersException('Geef een naam aan de lijst.');
	}
	$is_actief ??= false;
	$artiest_eenmalig ??= false;
	if ( $minkeuzes === false ) {
		throw new GebruikersException('Het minimaal aantal keuzes moet meer dan één zijn.');
	}
	if ( $maxkeuzes === false ) {
		throw new GebruikersException('Het maximaal aantal keuzes moet meer dan één zijn.');
	}
	if ( $maxkeuzes < $minkeuzes ) {
		throw new GebruikersException('Het maximum aantal keuzes kan niet lager zijn dan het minimum.');
	}
	if ( $stemmen_per_ip < 1 ) {
		$stemmen_per_ip = null;
	}
	return [
		'naam' => $naam,
		'actief'=> $is_actief,
		'minkeuzes' => $minkeuzes,
		'maxkeuzes' => $maxkeuzes,
		'stemmen_per_ip' => $stemmen_per_ip,
		'artiest_eenmalig' => $artiest_eenmalig,
		'recaptcha' => $recaptcha,
		'email' => $emails_str,
		'bedankt_tekst' => $bedankt_tekst
	];
}

function set_lijst_velden( Lijst $lijst, array $input_velden ) {
	foreach( $input_velden as $input_id => $input_veld ) {
		$id = filter_var($input_id, FILTER_VALIDATE_INT);
		$tonen = filter_var($input_veld['tonen'], FILTER_VALIDATE_BOOL) ?? false;
		$verplicht = filter_var($input_veld['verplicht'], FILTER_VALIDATE_BOOL) ?? false;
		$veld = new Veld($id);
		if ( $tonen ) {
			$lijst->set_veld($veld, $verplicht);
		} else {
			$lijst->remove_veld($veld);
		}
	}
}

/**
 * Bestaande lijst opslaan in de beheerdersinterface.
 */
function lijst_opslaan(): void {
    login();
	$lijst = Lijst::maak_uit_request();
	$data = filter_lijst_metadata();
	DB::updateMulti('lijsten', $data, "id = {$lijst->get_id()}");
	set_lijst_velden($lijst, filter_input(INPUT_POST, 'velden', FILTER_DEFAULT, FILTER_FORCE_ARRAY));
}

/**
 * Nieuwe lijst maken in de beheerdersinterface.
 * @return int ID van de nieuwe lijst.
 */
function lijst_maken(): int {
    login();
	$data = filter_lijst_metadata();
	$lijst = new Lijst(DB::insertMulti('lijsten', $data));
	set_lijst_velden($lijst, filter_input(INPUT_POST, 'velden', FILTER_REQUIRE_ARRAY));
	return $lijst->get_id();
}

/**
 * Losse nummers toevoegen aan de database.
 * @return array
 */
function losse_nummers_toevoegen(): array {
    login();
    $nummers = filter_input(INPUT_POST, 'nummers', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    $lijsten = filter_input(INPUT_POST, 'lijsten', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);
	if ( !isset($lijsten) ) {
		$lijsten = [];
	}
    $json = [];
	$json['toegevoegd'] = 0;
	$json['dubbel'] = 0;
	foreach( $nummers as $nummer ) {
		$artiest = trim($nummer['artiest']);
		$titel = trim($nummer['titel']);
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
				foreach( $lijsten as $lijst ) {
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
 */
function stem_set_behandeld(): void {
    login();
	$stem = Stem::maak_uit_request();
	$waarde = filter_input(INPUT_POST, 'waarde', FILTER_VALIDATE_BOOL);
	$stem->set_behandeld($waarde);
}

/**
 * Verwijderen van een stem in de resultateninterface.
 */
function verwijder_stem(): void {
    login();
    $lijst = Lijst::maak_uit_request();
	$stem = new Stem(
		Nummer::maak_uit_request(),
		$lijst,
		Stemmer::maak_uit_request()
	);
	$stem->verwijderen();
}

/**
 * Verwijderen van een nummer in de resultateninterface.
 */
function verwijder_nummer(): void {
    login();
    $lijst = Lijst::maak_uit_request();
	$lijst->verwijder_nummer(Nummer::maak_uit_request());
}

/**
 * Geeft het totaal aantal stemmers op een lijst.
 */
function get_totaal_aantal_stemmers(): int {
    login();
    $lijst = Lijst::maak_uit_request();
    $van = filter_input_van_tot('van');
    $tot = filter_input_van_tot('tot');
    return count($lijst->get_stemmers($van, $tot));
}

/**
 * Verwerk een stem van een bezoeker.
 * @return string HTML-respons.
 */
function stem(): string {
    $lijst = Lijst::maak_uit_request();
	if ( $lijst->heeft_gebruik_recaptcha() && !is_captcha_ok() ) {
		throw new GebruikersException('Captcha verkeerd.');
	}
	if ( $lijst->is_max_stemmen_per_ip_bereikt($lijst) ) {
		return 'Het maximum aantal stemmen voor dit IP-adres is bereikt.';
	}
	try {
		$stemmer = verwerk_stem($lijst);
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

function verwerk_stem( Lijst $lijst ): Stemmer {
	check_ip_blacklist($_SERVER['REMOTE_ADDR']);
	$stemmer = Stemmer::maak(
		$_SERVER['REMOTE_ADDR']
	);
    
	$input_nummers = filter_input(INPUT_POST, 'nummers', FILTER_DEFAULT, FILTER_FORCE_ARRAY);
    foreach( $input_nummers as $input_nummer ) {
		$nummer = new Nummer(filter_var($input_nummer['id'], FILTER_VALIDATE_INT));
		$toelichting = filter_var($input_nummer['toelichting']);
        $stemmer->add_stem($nummer, $lijst, $toelichting);
    }
	
	// Invoer van velden
	$input_velden = filter_input(INPUT_POST, 'velden', FILTER_DEFAULT, FILTER_FORCE_ARRAY);
	foreach ( $lijst->get_velden() as $veld ) {
		try {
			$waarde = $input_velden[$veld->get_id()];
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
 */
function lijst_nummer_toevoegen(): void {
    login();
    DB::disableAutocommit();
    $lijst = Lijst::maak_uit_request();
    $lijst->nummer_toevoegen(Nummer::maak_uit_request());
    DB::commit();
}

/**
 * Haalt een nummer weg uit een stemlijst.
 */
function lijst_nummer_verwijderen(): void {
    login();
    DB::disableAutocommit();
    $lijst = Lijst::maak_uit_request();
    $lijst->verwijder_nummer(Nummer::maak_uit_request());
    DB::commit();
}

function get_selected_html(): string {
    try {
        $lijst = Lijst::maak_uit_request();
        $nummers = $lijst->get_nummers_sorteer_titels();
        $aantal = count($nummers);
        $titel_aantal = " ({$aantal})";
    } catch ( Muzieklijsten_Exception $e ) {
        $nummers = [];
        $titel_aantal = '';
    }

    $tbody = '';
    foreach ( $nummers as $nummer ) {
        $titel = htmlspecialchars($nummer->get_titel());
        $artiest = htmlspecialchars($nummer->get_artiest());
        $jaar = $nummer->get_jaar();
        $tbody .= <<<EOT
            <tr>
                <td>{$titel}</td>
                <td>{$artiest}</td>
                <td>{$jaar}</td>
            </tr>
        EOT;
    }
    return <<<EOT
    <h4>Geselecteerd{$titel_aantal}</h4>
    <hr>
    <table class="table table-striped">
        <thead>
        <tr>
            <th>Titel</th>
            <th>Artiest</th>
            <th>Jaar</th>
        </tr>
        </thead>
        <tbody>{$tbody}</tbody>
    </table>
    EOT;
}

function vul_datatables(): array {
    $ssp = new SSP(
        INPUT_POST,
        'nummers',
        'id',
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
    return $ssp->simple();
}

/**
 * Geeft data over de stemlijst voor de stempagina.
 * @return array
 */
function get_stemlijst_frontend_data(): array {
	try {
		$lijst = Lijst::maak_uit_request();
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

function get_resultaten_labels(): array {
	login();
	try {
		$lijst = Lijst::maak_uit_request();
	} catch ( SQLException ) {
		throw new GebruikersException('Ongeldige lijst');
	}
	$respons = [];
	foreach ( $lijst->get_velden() as $veld ) {
		$respons[] = $veld->get_label();
	}
	return $respons;
}

function get_resultaten(): array {
	login();
	try {
		$lijst = Lijst::maak_uit_request();
	} catch ( SQLException ) {
		throw new GebruikersException('Ongeldige lijst');
	}
	return $lijst->get_resultaten();
}

try {
    DB::disableAutocommit();
    $functie = filter_input(INPUT_POST, 'functie');
    $respons = [];
    $data = null;
    switch ($functie) {
		case 'get_stemlijst_frontend_data':
			$data = get_stemlijst_frontend_data();
			break;
        case 'verwijder_lijst':
            verwijder_lijst();
            break;
        case 'lijst_opslaan':
            lijst_opslaan();
            break;
		case 'lijst_maken':
			$data = lijst_maken();
			break;
        case 'losse_nummers_toevoegen':
            $data = losse_nummers_toevoegen();
            break;
        case 'get_lijsten':
            $data = ajax_get_lijsten();
            break;
        case 'stem_set_behandeld':
            stem_set_behandeld();
            break;
        case 'verwijder_stem':
            verwijder_stem();
            break;
        case 'verwijder_nummer':
            verwijder_nummer();
            break;
        case 'get_totaal_aantal_stemmers':
            $data = get_totaal_aantal_stemmers();
            break;
        case 'stem':
            $data = stem();
            break;
        case 'lijst_nummer_toevoegen':
            lijst_nummer_toevoegen();
            break;
        case 'lijst_nummer_verwijderen':
            lijst_nummer_verwijderen();
            break;
        case 'vul_datatables':
            $data = vul_datatables();
            break;
        case 'get_selected_html':
            $data = get_selected_html();
            break;
		case 'login':
			login();
			break;
		case 'get_resultaten_labels':
			$data = get_resultaten_labels();
			break;
		case 'get_resultaten':
			$data = get_resultaten();
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
