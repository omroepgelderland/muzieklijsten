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
	$veld_telefoonnummer = filter_input(INPUT_POST, 'veld-telefoonnummer', FILTER_VALIDATE_BOOL);
	$veld_email = filter_input(INPUT_POST, 'veld-email', FILTER_VALIDATE_BOOL);
	$veld_woonplaats = filter_input(INPUT_POST, 'veld-woonplaats', FILTER_VALIDATE_BOOL);
	$veld_adres = filter_input(INPUT_POST, 'veld-adres', FILTER_VALIDATE_BOOL);
	$veld_uitzenddatum = filter_input(INPUT_POST, 'veld-uitzenddatum', FILTER_VALIDATE_BOOL);
	$veld_vrijekeus = filter_input(INPUT_POST, 'veld-vrijekeus', FILTER_VALIDATE_BOOL);
	$recaptcha = filter_input(INPUT_POST, 'recaptcha', FILTER_VALIDATE_BOOL);
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
	$veld_telefoonnummer ??= false;
	$veld_email ??= false;
	$veld_woonplaats ??= false;
	$veld_adres ??= false;
	$veld_uitzenddatum ??= false;
	$veld_vrijekeus ??= false;
	$recaptcha ??= false;
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
		'veld_telefoonnummer' => $veld_telefoonnummer,
		'veld_email' => $veld_email,
		'veld_woonplaats' => $veld_woonplaats,
		'veld_adres' => $veld_adres,
		'veld_uitzenddatum' => $veld_uitzenddatum,
		'veld_vrijekeus' => $veld_vrijekeus,
		'recaptcha' => $recaptcha,
		'email' => $emails_str,
		'bedankt_tekst' => $bedankt_tekst
	];
}

/**
 * Bestaande lijst opslaan in de beheerdersinterface.
 */
function lijst_opslaan(): void {
    login();
	$lijst = Lijst::maak_uit_request();
	$data = filter_lijst_metadata();
	DB::updateMulti('lijsten', $data, "id = {$lijst->get_id()}");
}

/**
 * Nieuwe lijst maken in de beheerdersinterface.
 * @return int ID van de nieuwe lijst.
 */
function lijst_maken(): int {
    login();
	$data = filter_lijst_metadata();
	return DB::insertMulti('lijsten', $data);
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
 * Toon gekozen nummers aan de bezoeker.
 * @return string HTML.
 */
function toon_geselecteerde_nummers(): string {
    $nummer_ids = filter_input(INPUT_POST, 'nummers', FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY);
    $nummers = [];
    if ( $nummer_ids !== null ) {
        foreach ( $nummer_ids as $nummer_id ) {
            $nummers[] = new Nummer($nummer_id);
        }
    }
    $html = <<<EOT
        <h4>Uw keuze</h4>
        <table class="table" id="keuzes">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Artiest</th>
                    <th>Toelichting</th>
                </tr>
            </thead>
            <tbody>
    EOT;
	foreach ( $nummers as $nummer ) {
		$titel = htmlspecialchars($nummer->get_titel());
		$artiest = htmlspecialchars($nummer->get_artiest());
		$html .= <<<EOT
			<tr>
				<td>{$titel}</td>
				<td>{$artiest}</td>
				<td class="remark">
					<input name="id_{$nummer->get_id()}" type="text" class="form-control">
				</td>
			</tr>
		EOT;
	}
	$html .= '</tbody></table>';
	return $html;
}

/**
 * Verwerk een stem van een bezoeker.
 * @return string HTML-respons.
 */
function stem(): string {
    $lijst = Lijst::maak_uit_request();
    $nummer_ids = filter_input(INPUT_POST, 'nummers', FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY);
    $nummers = [];
    if ( $nummer_ids !== null ) {
        foreach ( $nummer_ids as $nummer_id ) {
            $nummers[] = new Nummer($nummer_id);
        }
    }
	if ( $lijst->heeft_gebruik_recaptcha() && !is_captcha_ok() ) {
		throw new GebruikersException('Captcha verkeerd.');
	}
	if ( $lijst->is_max_stemmen_per_ip_bereikt($lijst) ) {
		return 'Het maximum aantal stemmen voor dit IP-adres is bereikt.';
	}
	$stemmer = verwerk_stem($lijst, $nummers);
	$bedankt_tekst = htmlspecialchars($lijst->get_bedankt_tekst());
	$html = "<h4>{$bedankt_tekst}</h4>";
	if ( $lijst->get_id() == 31 || $lijst->get_id() == 201 ) {
		if ( is_dev() ) {
			$fbshare_url = sprintf(
				'http://192.168.1.107/%s/muzieklijsten/fbshare.php?stemmer=%d',
				get_developer(),
				$stemmer->get_id()
			);
			$fb_url = 'https://www.facebook.com/sharer/sharer.php?u=http%3A%2F%2F192.168.1.107%2F'.get_developer().'%2Fmuzieklijsten%2Ffbshare.php%3Fstemmer%3D'.$stemmer->get_id().'&amp;src=sdkpreparse';
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

function verwerk_stem( Lijst $lijst, array $nummers ): Stemmer {
	$invoer = (object)filter_input_array(INPUT_POST, [
		'naam' => FILTER_DEFAULT,
		'woonplaats' => FILTER_DEFAULT,
		'adres' => FILTER_DEFAULT,
		'veld_uitzenddatum' => FILTER_DEFAULT,
		'veld_vrijekeus' => FILTER_DEFAULT
	]);
	$invoer->veld_email = filter_input(INPUT_POST, 'veld_email', FILTER_SANITIZE_EMAIL);
	if ( $invoer->veld_email !== null ) {
		$invoer->veld_email = strtolower($invoer->veld_email);
	}
	$invoer->postcode = filter_input_postcode();
	$invoer->telefoonnummer = filter_input_telefoonnummer();
	$stemmer = Stemmer::maak(
		$invoer->naam,
		$invoer->adres,
		$invoer->postcode,
		$invoer->woonplaats,
		$invoer->telefoonnummer,
		$invoer->veld_email,
		$invoer->veld_uitzenddatum,
		$invoer->veld_vrijekeus,
		$_SERVER['REMOTE_ADDR']
	);
    
    foreach( $nummers as $nummer ) {
        $stemmer->add_stem($nummer, $lijst, $nummer->get_post_toelichting());
    }
	
	// Invoer van extra velden
	foreach ( $lijst->get_extra_velden() as $extra_veld ) {
		$waarde = filter_input(INPUT_POST, $extra_veld->get_html_id(), FILTER_DEFAULT);
		if ( $waarde !== false && $waarde !== null ) {
			$extra_veld->add_waarde($stemmer, $waarde);
		} elseif ( $extra_veld->is_verplicht() ) {
			throw new GebruikersException(sprintf(
				'Veld %s is verplicht voor de lijst %s',
				$extra_veld->get_label(),
				$lijst->get_naam()
			));
		}
	}

	$stemmer->mail_redactie($lijst);
	return $stemmer;
}

/**
 * Voegt nieuwe nummers toe aan een lijst en verwijdert nummers.
 */
function update_lijst(): void {
    login();
    $lijst = Lijst::maak_uit_request();
    $nummer_ids = filter_input(INPUT_POST, 'nummers', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);

    DB::disableAutocommit();
    $values_lijst_str = implode(",{$lijst->get_id()}),(", $nummer_ids);
    $query = <<<EOT
        INSERT IGNORE into lijsten_nummers (nummer_id, lijst_id)
        VALUES ({$values_lijst_str},{$lijst->get_id()})
    EOT;
    DB::query($query);
    
    $nummer_ids_str = implode(',', $nummer_ids);
    $query = <<<EOT
        DELETE FROM lijsten_nummers
        WHERE
            lijst_id = {$lijst->get_id()}
            AND nummer_id NOT IN ({$nummer_ids_str})
    EOT;
    DB::query($query);
    verwijder_stemmers_zonder_stemmen();
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

try {
    DB::disableAutocommit();
    $functie = filter_input(INPUT_POST, 'functie');
    $respons = [];
    $data = null;
    switch ($functie) {
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
        case 'toon_geselecteerde_nummers':
            $data = toon_geselecteerde_nummers();
            break;
        case 'stem':
            $data = stem();
            break;
        case 'update_lijst':
            update_lijst();
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
    Log::err(json_encode($_POST));
    Log::err(json_encode($_SERVER));
    Log::err(json_encode($_REQUEST));
    $respons['error'] = true;
    $respons['errordata'] = is_dev()
    ? $e->getMessage()
    : 'fout';
}
header('Content-Type: application/json');
echo json_encode($respons);
