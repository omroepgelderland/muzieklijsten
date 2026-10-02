<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

use gldstdlib\exception\GLDException;
use gldstdlib\exception\SQLDupEntryException;
use gldstdlib\exception\SQLException;
use gldstdlib\exception\UndefinedPropertyException;
use gldstdlib\OpenAIClient;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use ZipArchive;

use function gldstdlib\vervang_bestandsnaam_tekens;

/**
 * Verwerking van AJAX-requests.
 * Alleen functies die rechtstreeks door de frontend mogen worden aangeroepen
 * moeten public zijn.
 *
 * @phpstan-import-type VeldenData from Lijst
 * @phpstan-import-type Resultaten from Lijst
 * @phpstan-import-type ModVrijeKeuzeData from Nummer as ModVrijeKeuzeNummerData
 */
class Ajax
{
    /**
     * @param object $request Requestdata van de frontend.
     */
    public function __construct(
        private Factory $factory,
        private Config $config,
        private DB $db,
        private readonly ?OpenAIClient $openai_client,
        private object $request,
    ) {
    }

    /**
     * Geeft data over de stemlijst voor de stempagina.
     *
     * @return array{
     *     minkeuzes: int,
     *     maxkeuzes: int,
     *     vrijekeuzes: int,
     *     is_artiest_eenmalig: bool,
     *     organisatie: string,
     *     lijst_naam: string,
     *     heeft_gebruik_recaptcha: bool,
     *     is_actief: bool,
     *     velden: list<array{
     *         id: int,
     *         label: string,
     *         leeg_feedback: string,
     *         type: string,
     *         verplicht: bool,
     *         max: int,
     *         maxlength: int,
     *         min: int,
     *         minlength: int,
     *         placeholder: string
     *     }>,
     *     recaptcha_sitekey: ?string,
     *     privacy_url: string,
     *     random_volgorde: bool
     * }
     *
     * @throws GeenLijstException
     */
    public function get_stemlijst_frontend_data(): array
    {
        try {
            $lijst = $this->factory->create_lijst_uit_request($this->request);
            $lijst->get_naam(); // Forceer dat de lijst in de database wordt geopend.
        } catch (SQLException) {
            throw new GebruikersException('Ongeldige lijst');
        }

        $velden = [];
        foreach ($lijst->get_velden() as $veld) {
            $velddata = [
                'id' => $veld->get_id(),
                'label' => $veld->get_label(),
                'leeg_feedback' => $veld->get_leeg_feedback(),
                'type' => $veld->get_type(),
                'verplicht' => $veld->is_verplicht(),
            ];
            try {
                $velddata['max'] = $veld->get_max();
            } catch (ObjectEigenschapOntbreekt) {
            }
            try {
                $velddata['maxlength'] = $veld->get_maxlength();
            } catch (ObjectEigenschapOntbreekt) {
            }
            try {
                $velddata['min'] = $veld->get_min();
            } catch (ObjectEigenschapOntbreekt) {
            }
            try {
                $velddata['minlength'] = $veld->get_minlength();
            } catch (ObjectEigenschapOntbreekt) {
            }
            try {
                $velddata['placeholder'] = $veld->get_placeholder();
            } catch (ObjectEigenschapOntbreekt) {
            }
            $velden[] = $velddata;
        }

        return [
            'minkeuzes' => $lijst->get_minkeuzes(),
            'maxkeuzes' => $lijst->get_maxkeuzes(),
            'vrijekeuzes' => $lijst->get_vrijekeuzes(),
            'is_artiest_eenmalig' => $lijst->is_artiest_eenmalig(),
            'organisatie' => $this->config->get()['organisatie'],
            'lijst_naam' => $lijst->get_naam(),
            'heeft_gebruik_recaptcha' => $lijst->heeft_gebruik_recaptcha(),
            'is_actief' => $lijst->is_actief(),
            'velden' => $velden,
            'recaptcha_sitekey' => $this->config->heeft_recaptcha_keys()
                ? $this->config->get()['recaptcha']['sitekey']
                : null,
            'privacy_url' => $this->config->get()['privacy_url'],
            'random_volgorde' => $lijst->is_random_volgorde(),
        ];
    }

    /**
     * Lijst verwijderen vanuit de beheerdersinterface.
     *
     * @throws GeenLijstException
     */
    public function verwijder_lijst(): void
    {
        $this->login();
        $lijst = $this->factory->create_lijst_uit_request($this->request);
        $lijst->verwijderen();
    }

    /**
     * Bestaande lijst opslaan in de beheerdersinterface.
     *
     * @throws GeenLijstException
     */
    public function lijst_opslaan(): void
    {
        $this->login();
        $lijst = $this->factory->create_lijst_uit_request($this->request);
        $data = $this->filter_lijst_metadata();
        $this->db->updateMulti('lijsten', $data, "id = {$lijst->get_id()}");
        try {
            $velden = $this->request->velden;
        } catch (UndefinedPropertyException) {
            $velden = new \stdClass();
        }
        $this->set_lijst_velden($lijst, $velden);
    }

    /**
     * Haalt metadata van een lijst uit het request.
     *
     * @return array{
     *     naam: string,
     *     actief: bool,
     *     minkeuzes: positive-int,
     *     maxkeuzes: positive-int,
     *     vrijekeuzes: int<0, max>,
     *     stemmen_per_ip: ?positive-int,
     *     artiest_eenmalig: bool,
     *     mail_stemmers: bool,
     *     random_volgorde: bool,
     *     recaptcha: bool,
     *     email: string,
     *     bedankt_tekst: string
     * }
     */
    private function filter_lijst_metadata(): array
    {
        $naam = trim(filter_var($this->request->naam));
        $is_actief = isset($this->request->{'is-actief'});
        $minkeuzes = (int)filter_var($this->request->minkeuzes, \FILTER_VALIDATE_INT);
        $maxkeuzes = (int)filter_var($this->request->maxkeuzes, \FILTER_VALIDATE_INT);
        $vrijekeuzes = (int)filter_var($this->request->vrijekeuzes, \FILTER_VALIDATE_INT);
        /** @var ?int $stemmen_per_ip */
        $stemmen_per_ip = filter_var($this->request->{'stemmen-per-ip'}, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE);
        $artiest_eenmalig = isset($this->request->{'artiest-eenmalig'});
        $mail_stemmers = isset($this->request->{'mail-stemmers'});
        $random_volgorde = isset($this->request->{'random-volgorde'});
        $recaptcha = isset($this->request->recaptcha);
        $emails = explode(',', filter_var($this->request->email));
        $emails_geparsed = [];
        foreach ($emails as $email) {
            $email = trim(strtolower($email));
            if ($email === '') {
                continue;
            }
            $email_geparsed = filter_var($email, \FILTER_VALIDATE_EMAIL);
            if ($email_geparsed === false) {
                throw new GebruikersException("Ongeldig e-mailadres: \"{$email}\"");
            }
            $emails_geparsed[] = $email_geparsed;
        }
        $emails_str = implode(',', $emails_geparsed);
        $bedankt_tekst = trim(filter_var($this->request->{'bedankt-tekst'}));

        if ($naam === '') {
            throw new GebruikersException('Geef een naam aan de lijst.');
        }
        if ($minkeuzes < 0) {
            throw new GebruikersException('Het minimum aantal keuzes moet positief zijn.');
        }
        if ($maxkeuzes < 1) {
            throw new GebruikersException('Het maximum aantal keuzes moet minstens één zijn.');
        }
        if ($maxkeuzes < $minkeuzes) {
            throw new GebruikersException('Het maximum aantal keuzes kan niet lager zijn dan het minimum.');
        }
        $vrijekeuzes = max(0, $vrijekeuzes);
        if ($stemmen_per_ip < 1) {
            $stemmen_per_ip = null;
        }
        return [
            'naam' => $naam,
            'actief' => $is_actief,
            'minkeuzes' => $minkeuzes,
            'maxkeuzes' => $maxkeuzes,
            'vrijekeuzes' => $vrijekeuzes,
            'stemmen_per_ip' => $stemmen_per_ip,
            'artiest_eenmalig' => $artiest_eenmalig,
            'mail_stemmers' => $mail_stemmers,
            'random_volgorde' => $random_volgorde,
            'recaptcha' => $recaptcha,
            'email' => $emails_str,
            'bedankt_tekst' => $bedankt_tekst,
        ];
    }

    private function set_lijst_velden(Lijst $lijst, object $input_velden): void
    {
        $alle_velden = $this->factory->get_velden();
        foreach ($alle_velden as $veld) {
            try {
                $id = $veld->get_id();
                $input_veld = $input_velden->$id;
                $tonen = isset($input_veld->tonen);
                $verplicht = isset($input_veld->verplicht);
                if ($tonen) {
                    $lijst->set_veld($veld, $verplicht);
                } else {
                    $lijst->remove_veld($veld);
                }
            } catch (UndefinedPropertyException) {
                $lijst->remove_veld($veld);
            }
        }
    }

    /**
     * Nieuwe lijst maken in de beheerdersinterface.
     *
     * @return int ID van de nieuwe lijst.
     */
    public function lijst_maken(): int
    {
        $this->login();
        $data = $this->filter_lijst_metadata();
        $lijst = $this->factory->create_lijst($this->db->insertMulti('lijsten', $data));
        try {
            $velden = $this->request->velden;
        } catch (UndefinedPropertyException) {
            $velden = new \stdClass();
        }
        $this->set_lijst_velden($lijst, $velden);
        return $lijst->get_id();
    }

    /**
     * Losse nummers toevoegen aan de database.
     *
     * @return array{
     *     toegevoegd: int<0, max>,
     *     dubbel: int<0, max>,
     *     lijsten_nummers: int<0, max>
     * }
     */
    public function losse_nummers_toevoegen(): array
    {
        $this->login();
        $this->request->nummers ??= [];
        $this->request->lijsten ??= [];
        $json = [
            'toegevoegd' => 0,
            'dubbel' => 0,
            'lijsten_nummers' => 0,
        ];
        foreach ($this->request->nummers as $nummer) {
            $artiest = trim($nummer->artiest);
            $titel = trim($nummer->titel);
            $jaar = \filter_var($nummer->jaar ?? null, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE);
            $jaar = isset($jaar) ? (int)$jaar : null;
            $duur_str = \trim($nummer->duur ?? '');
            $duur_res = \preg_match('/^([0-5]?[0-9])(?:\:|\.)([0-5][0-9])$/', $duur_str, $m);
            if ($duur_res === 1) {
                $duur = (int)$m[1] * 60 + (int)$m[2];
            } else {
                $duur = null;
            }
            if ($titel !== '' && $artiest !== '') {
                $vgl_artiest = $this->db->escape_string(get_vgl_string($artiest, true));
                $vgl_titel = $this->db->escape_string(get_vgl_string($titel, false));
                $jaar_cond = isset($jaar) ? "AND (`jaar` = {$jaar} OR `jaar` IS NULL)" : '';
                $sql = <<<EOT
                SELECT id
                FROM nummers
                WHERE
                    `vgl_artiest` = "{$vgl_artiest}"
                    AND `vgl_titel` = "{$vgl_titel}"
                    {$jaar_cond}
                EOT;
                $res = $this->db->query($sql);
                if ($res->num_rows > 0) {
                    $json['dubbel']++;
                    $nummer_id = (int)$res->fetch_array()[0];
                } else {
                    $json['toegevoegd']++;
                    $nummer = $this->factory->insert_nummer(
                        $titel,
                        $artiest,
                        jaar: $jaar,
                        duur: $duur,
                    );
                    $nummer_id = $nummer->get_id();
                }
                foreach ($this->request->lijsten as $lijst) {
                    try {
                        $this->db->insertMulti('lijsten_nummers', [
                            'nummer_id' => $nummer_id,
                            'lijst_id' => $lijst,
                        ]);
                        $json['lijsten_nummers']++;
                    } catch (SQLDupEntryException) {
                    }
                }
            }
        }
        return $json;
    }

    /**
     * Geeft alle lijsten voor het toevoegen van losse nummers.
     *
     * @return list<array{
     *     id: positive-int,
     *     naam: string
     * }>
     */
    public function get_lijsten(): array
    {
        $respons = [];
        foreach ($this->factory->get_muzieklijsten() as $lijst) {
            $respons[] = [
                'id' => $lijst->get_id(),
                'naam' => $lijst->get_naam(),
            ];
        }
        return $respons;
    }

    /**
     * Instellen dat een stem is behandeld door de redactie.
     *
     * @throws GeenLijstException
     */
    public function stem_set_behandeld(): void
    {
        $this->login();
        $stem = $this->factory->create_stemmer_nummer_uit_request($this->request);
        $waarde = filter_var($this->request->waarde, \FILTER_VALIDATE_BOOL);
        $stem->set_behandeld($waarde);
    }

    /**
     * Verwijderen van een stem in de resultateninterface.
     *
     * @throws GeenLijstException
     */
    public function verwijder_stem(): void
    {
        $this->login();
        $stem = $this->factory->create_stemmer_nummer_uit_request($this->request);
        $stem->verwijderen();
        $this->db->verwijder_ongekoppelde_vrije_keuze_nummers();
    }

    /**
     * Verwijderen van een nummer in de resultateninterface.
     * (wordt momenteel niet gebruikt)
     *
     * @throws GeenLijstException
     */
    public function verwijder_nummer(): void
    {
        $this->login();
        $lijst = $this->factory->create_lijst_uit_request($this->request);
        $lijst->verwijder_nummer($this->factory->create_nummer_uit_request($this->request));
    }

    /**
     * Geeft het totaal aantal stemmers op een lijst.
     *
     * @throws GeenLijstException
     */
    public function get_totaal_aantal_stemmers(): int
    {
        $this->login();
        $lijst = $this->factory->create_lijst_uit_request($this->request);
        try {
            $van = filter_van_tot($this->request->van);
        } catch (UndefinedPropertyException) {
            $van = null;
        }
        try {
            $tot = filter_van_tot($this->request->tot);
        } catch (UndefinedPropertyException) {
            $tot = null;
        }
        return count($lijst->get_stemmers($van, $tot));
    }

    /**
     * Verwerk een stem van een bezoeker.
     *
     * @return string HTML-respons.
     *
     * @throws GeenLijstException
     */
    public function stem(): string
    {
        $lijst = $this->factory->create_lijst_uit_request($this->request);
        $bedankt_tekst = htmlspecialchars($lijst->get_bedankt_tekst());
        $html = "<h4>{$bedankt_tekst}</h4>";

        if (
            $lijst->heeft_gebruik_recaptcha()
            && !$this->config->is_captcha_ok($this->request->{'g-recaptcha-response'})
        ) {
            throw new GebruikersException('Captcha verkeerd.');
        }
        if ($lijst->is_max_stemmen_per_ip_bereikt()) {
            return $html;
        }

        try {
            $stemmer = $this->verwerk_stem($lijst);
            if ($lijst->get_id() == 31 || $lijst->get_id() == 201) {
                if (is_dev()) {
                    $fbshare_url = sprintf(
                        'https://webdev.gld.nl/%s/muzieklijsten/fbshare.php?stemmer=%d',
                        get_developer(),
                        $stemmer->get_id()
                    );
                    $fb_url = 'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fwebdev.gld.nl%2F'
                        . get_developer()
                        . '%2Fmuzieklijsten%2Ffbshare.php%3Fstemmer%3D'
                        . $stemmer->get_id() . '&amp;src=sdkpreparse';
                } else {
                    $fbshare_url = 'https://web.omroepgelderland.nl/muzieklijsten/fbshare.php?stemmer='
                        . $stemmer->get_id();
                    $fb_url = 'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fweb.omroepgelderland.nl'
                    . '%2Fmuzieklijsten%2Ffbshare.php%3Fstemmer%3D' . $stemmer->get_id() . '&amp;src=sdkpreparse';
                }
                $html .= <<<EOT
                <div class="fb-share-button" data-href="{$fbshare_url}" data-layout="button" data-size="large" data-mobile-iframe="true">
                    <a class="fb-xfbml-parse-ignore" target="_blank" href="{$fb_url}">Deel mijn keuze op Facebook</a>
                </div>
                EOT;
            }
        } catch (BlacklistException) {
        }
        return $html;
    }

    /**
     * Verwerkt een steminvoer.
     * Als er al een stem is geweest op deze lijst met hetzelfde e-mailadres of
     * telefoonnummer dan wordt de oude stem gewist en vervangen door de nieuwe
     * invoer. De gebruiker wordt hier niet van op de hoogte gesteld.
     *
     * @throws BlacklistException
     */
    private function verwerk_stem(Lijst $lijst): Stemmer
    {
        $this->db->check_ip_blacklist($_SERVER['REMOTE_ADDR']);

        // Zoek bestaande stemmer.
        $stemmer = null;
        try {
            $stemmer = $lijst->get_stemmer_uit_telefoonnummer($this->request->velden->{5});
        } catch (UndefinedPropertyException) {
        }
        try {
            $stemmer ??= $lijst->get_stemmer_uit_email($this->request->velden->{6});
        } catch (UndefinedPropertyException) {
        }

        // Verwijder vorige stem en invoer.
        $stemmer?->verwijder_stemmen();
        $stemmer?->verwijder_velden();

        // Maak nieuwe stemmer.
        $stemmer ??= $this->factory->insert_stemmer(
            $lijst,
            $_SERVER['REMOTE_ADDR']
        );

        $this->request->nummers ??= new \stdClass();
        foreach ($this->request->nummers as $input_nummer) {
            $nummer = $this->factory->create_nummer(filter_var($input_nummer->id, \FILTER_VALIDATE_INT));
            $toelichting = filter_var($input_nummer->toelichting);
            $stemmer->add_stem($nummer, $toelichting, false);
        }

        // Invoer van (optionele) vrije keuzes
        try {
            $vrijekeuzes = $this->request->vrijekeuzes;
        } catch (UndefinedPropertyException) {
            $vrijekeuzes = [];
        }
        foreach ($vrijekeuzes as $vrijekeus_invoer) {
            try {
                $nummer = $this->factory->vrijekeuze_nummer_toevoegen(
                    $vrijekeus_invoer->artiest,
                    $vrijekeus_invoer->titel
                );
                if ($nummer->is_vrijekeuze() === 0) {
                    foreach ($lijst->get_nummers() as $lijst_nummer) {
                        if ($nummer->equals($lijst_nummer)) {
                            throw new GebruikersException(
                                "Het nummer {$lijst_nummer->get_artiest()} – {$lijst_nummer->get_titel()} kan niet "
                                . "worden gekozen als vrij nummer. Selecteer het nummer in de keuzelijst"
                            );
                        }
                    }
                }
                $stemmer->add_stem($nummer, $vrijekeus_invoer->toelichting, true);
            } catch (LegeVrijeKeuze) {
            }
        }

        if (\count((array)$this->request->nummers) + \count($vrijekeuzes) === 0) {
            throw new GebruikersException('Je hebt geen nummers gekozen.');
        }

        $stemmer->verwijder_ongeldige_stemmen();

        // Invoer van velden
        foreach ($lijst->get_velden() as $veld) {
            try {
                $id = $veld->get_id();
                $waarde = $this->request->velden->$id;
            } catch (UndefinedPropertyException) {
                $waarde = null;
            }
            $veld->set_waarde($stemmer, $waarde);
        }

        $stemmer->mail_redactie();
        $stemmer->mail_stemmer();
        return $stemmer;
    }

    /**
     * Voegt een nummer toe aan een stemlijst.
     *
     * @throws GeenLijstException
     */
    public function lijst_nummer_toevoegen(): void
    {
        $this->login();
        $this->db->disableAutocommit();
        $lijst = $this->factory->create_lijst_uit_request($this->request);
        $lijst->nummer_toevoegen($this->factory->create_nummer_uit_request($this->request));
        $this->db->commit();
    }

    /**
     * Haalt een nummer weg uit een stemlijst.
     *
     * @throws GeenLijstException
     */
    public function lijst_nummer_verwijderen(): void
    {
        $this->login();
        $this->db->disableAutocommit();
        $lijst = $this->factory->create_lijst_uit_request($this->request);
        $lijst->verwijder_nummer($this->factory->create_nummer_uit_request($this->request));
        $this->db->commit();
    }

    /**
     * @return list<array{
     *     id: positive-int,
     *     titel: string,
     *     artiest: string,
     *     jaar: int
     * }>
     */
    public function get_geselecteerde_nummers(): array
    {
        try {
            $lijst = $this->factory->create_lijst_uit_request($this->request);
            $nummers = $lijst->get_nummers_sorteer_titels();
        } catch (GLDException $e) {
            $nummers = [];
        }
        $respons = [];
        foreach ($nummers as $nummer) {
            $respons[] = [
                'id' => $nummer->get_id(),
                'titel' => $nummer->get_titel(),
                'artiest' => $nummer->get_artiest(),
                'jaar' => $nummer->get_jaar(),
            ];
        }
        return $respons;
    }

    /**
     * @return array{
     *     draw: int,
     *     recordsTotal: int,
     *     recordsFiltered: int,
     *     data: list<list<string>>
     * }
     *
     * @throws GeenLijstException
     */
    public function vul_datatables(): array
    {
        $ssp = $this->factory->create_ssp(
            $this->request,
            [
                [
                    'db' => 'id',
                    'dt' => 0,
                ], [
                    'db' => 'titel',
                    'dt' => 1,
                ], [
                    'db' => 'artiest',
                    'dt' => 2,
                ], [
                    'db' => 'jaar',
                    'dt' => 3,
                ],
            ]
        );
        $res = $ssp->simple();
        return $res;
    }

    /**
     * @return list<string>
     */
    public function get_resultaten_labels(): array
    {
        $this->login();
        try {
            $lijst = $this->factory->create_lijst_uit_request($this->request);
        } catch (GeenLijstException) {
            throw new GebruikersException('Ongeldige lijst');
        }
        $respons = [];
        foreach ($lijst->get_velden() as $veld) {
            $respons[] = $veld->get_label();
        }
        return $respons;
    }

    /**
     * @return Resultaten
     */
    public function get_resultaten(): array
    {
        $this->login();
        try {
            $lijst = $this->factory->create_lijst_uit_request($this->request);
        } catch (GeenLijstException) {
            throw new GebruikersException('Ongeldige lijst');
        }
        return $lijst->get_resultaten();
    }

    public function export_resultaten_ods(): void
    {
        $this->login();
        try {
            $lijst = $this->factory->create_lijst_uit_request($this->request);
        } catch (GeenLijstException) {
            throw new GebruikersException('Ongeldige lijst');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resultaten');

        $headers = [
            'Aantal stemmen',
            'Nummer ID',
            'Artiest',
            'Titel',
            'Jaar',
            'Duur',
            'Vrije keuze',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $row_nr = 2;
        /** @var array<int, int> $duur_per_rij */
        $duur_per_rij = [];
        foreach ($lijst->get_export_resultaten_nummers() as $resultaat) {
            $is_vrijekeuze = (int)$resultaat['is_vrijekeuze'];
            $vrije_keuze = match ($is_vrijekeuze) {
                0 => 'Nee',
                1 => 'Ja (niet beoordeeld)',
                2 => 'Ja (goedgekeurd)',
                default => 'Onbekend',
            };

            $sheet->setCellValue("A{$row_nr}", (int)$resultaat['aantal_stemmen']);
            $sheet->setCellValue("B{$row_nr}", (int)$resultaat['id']);
            $sheet->setCellValueExplicit("C{$row_nr}", $resultaat['artiest'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$row_nr}", $resultaat['titel'], DataType::TYPE_STRING);
            if ($resultaat['jaar'] !== null) {
                $sheet->setCellValue("E{$row_nr}", (int)$resultaat['jaar']);
            }
            if ($resultaat['duur'] !== null) {
                $duur_totaal_seconden = (int)$resultaat['duur'];
                $duur_per_rij[$row_nr] = $duur_totaal_seconden;
                $sheet->setCellValue("F{$row_nr}", $duur_totaal_seconden / 86400);
                $sheet->getStyle("F{$row_nr}")->getNumberFormat()->setFormatCode(
                    $duur_totaal_seconden >= 3600
                        ? NumberFormat::FORMAT_DATE_TIME4
                        : NumberFormat::FORMAT_DATE_TIME5
                );
            }
            $sheet->setCellValueExplicit("G{$row_nr}", $vrije_keuze, DataType::TYPE_STRING);
            $row_nr++;
        }

        if ($row_nr > 2) {
            $last_row = $row_nr - 1;
            $sheet->getStyle("A2:B{$last_row}")->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle("E2:E{$last_row}")->getNumberFormat()->setFormatCode('0');
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $timestamp = (new \DateTime())->format('Y-m-d H.i.s');
        $lijst_naam = $lijst->get_naam();
        $lijst_naam = trim(preg_replace('~\s+~u', ' ', $lijst_naam) ?? '');
        if ($lijst_naam === '') {
            $lijst_naam = (string)$lijst->get_id();
        }
        $filename = vervang_bestandsnaam_tekens(sprintf('%s - Resultaten ‘%s’.ods', $timestamp, $lijst_naam));
        $ascii_filename = preg_replace('~[^A-Za-z0-9 ._\-()\[\]{}]~', '_', $filename) ?? 'resultaten.ods';
        $ascii_filename = trim($ascii_filename);
        if ($ascii_filename === '') {
            $ascii_filename = 'resultaten.ods';
        }
        header('Content-Type: application/vnd.oasis.opendocument.spreadsheet');
        header(
            'Content-Disposition: attachment; filename="' . $ascii_filename . '"; '
            . "filename*=UTF-8''" . rawurlencode($filename)
        );
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $writer = new Ods($spreadsheet);
        $tmp_file = tempnam(sys_get_temp_dir(), 'resultaten_ods_');
        if ($tmp_file === false) {
            throw new GLDException('Kon tijdelijk exportbestand niet maken');
        }
        $writer->save($tmp_file);

        $zip = new ZipArchive();
        if ($zip->open($tmp_file) === true) {
            $content_xml = $zip->getFromName('content.xml');
            if ($content_xml !== false) {
                $nieuwe_content_xml = $this->apply_ods_time_styles_for_duration_column(
                    $content_xml,
                    $duur_per_rij
                );
                $zip->addFromString('content.xml', $nieuwe_content_xml);
            }
            $zip->close();
        }

        readfile($tmp_file);
        unlink($tmp_file);
    }

    /**
     * ODS-nabewerking voor de duurkolom (kolom F).
     *
     * Waarom dit nodig is:
     * PhpSpreadsheet schrijft in ODS numerieke tijdwaarden als gewone floats
     * (office:value-type="float") en genereert hierbij niet altijd de
     * benodigde ODS time-styles/data-styles. LibreOffice Calc toont die dan als
     * kale decimalen in plaats van tijdnotatie.
     *
     * Wat deze functie doet:
     * 1. Voegt expliciete ODS time-styles toe voor mm:ss en h:mm:ss.
     * 2. Zet duurcellen om naar office:value-type="time" met office:time-value.
     * 3. Past een afgeleide celstijl toe die de originele opmaak (zoals
     *    lettertypegrootte) behoudt en alleen de datastijl voor tijd toevoegt.
     *
     * @param array<int, int> $duur_per_rij Rij-index => duur in seconden.
     */
    private function apply_ods_time_styles_for_duration_column(
        string $content_xml,
        array $duur_per_rij
    ): string {
        if ($duur_per_rij === []) {
            return $content_xml;
        }

        $dom = new \DOMDocument();
        $dom->loadXML($content_xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
        $xpath->registerNamespace('style', 'urn:oasis:names:tc:opendocument:xmlns:style:1.0');
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('number', 'urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0');

        $automatic_styles = $xpath->query('/office:document-content/office:automatic-styles')->item(0);
        if (!($automatic_styles instanceof \DOMElement)) {
            return $content_xml;
        }

        $number_ns = 'urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0';
        $style_ns = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';

        $time_short = $dom->createElementNS($number_ns, 'number:time-style');
        $time_short->setAttributeNS($style_ns, 'style:name', 'NdurShort');
        $short_minutes = $dom->createElementNS($number_ns, 'number:minutes');
        $short_minutes->setAttributeNS($number_ns, 'number:style', 'long');
        $time_short->appendChild($short_minutes);
        $time_short->appendChild($dom->createElementNS($number_ns, 'number:text', ':'));
        $short_seconds = $dom->createElementNS($number_ns, 'number:seconds');
        $short_seconds->setAttributeNS($number_ns, 'number:style', 'long');
        $time_short->appendChild($short_seconds);
        $automatic_styles->appendChild($time_short);

        $time_long = $dom->createElementNS($number_ns, 'number:time-style');
        $time_long->setAttributeNS($style_ns, 'style:name', 'NdurLong');
        $long_hours = $dom->createElementNS($number_ns, 'number:hours');
        $long_hours->setAttributeNS($number_ns, 'number:style', 'long');
        $time_long->appendChild($long_hours);
        $time_long->appendChild($dom->createElementNS($number_ns, 'number:text', ':'));
        $long_minutes = $dom->createElementNS($number_ns, 'number:minutes');
        $long_minutes->setAttributeNS($number_ns, 'number:style', 'long');
        $time_long->appendChild($long_minutes);
        $time_long->appendChild($dom->createElementNS($number_ns, 'number:text', ':'));
        $long_seconds = $dom->createElementNS($number_ns, 'number:seconds');
        $long_seconds->setAttributeNS($number_ns, 'number:style', 'long');
        $time_long->appendChild($long_seconds);
        $automatic_styles->appendChild($time_long);

        /** @var array<string, string> $afgeleide_stijlen */
        $afgeleide_stijlen = [];
        /** @var array<string, \DOMElement> $bestaande_stijlen */
        $bestaande_stijlen = [];
        foreach ($xpath->query('style:style', $automatic_styles) as $stijl_node) {
            if (!($stijl_node instanceof \DOMElement)) {
                continue;
            }
            $stijl_naam = $stijl_node->getAttribute('style:name');
            if ($stijl_naam !== '') {
                $bestaande_stijlen[$stijl_naam] = $stijl_node;
            }
        }

        $table_rows = $xpath->query('(//table:table)[1]/table:table-row');
        if ($table_rows === false) {
            return $content_xml;
        }

        foreach ($duur_per_rij as $rij_nr => $duur_seconden) {
            $row_index = $rij_nr - 1;
            $row_node = $table_rows->item($row_index);
            if (!($row_node instanceof \DOMElement)) {
                continue;
            }

            $cell = $this->get_ods_cell_by_column_index($row_node, 6);
            if (!($cell instanceof \DOMElement)) {
                continue;
            }

            $heeft_uren = $duur_seconden >= 3600;
            $basis_stijl = $cell->getAttribute('table:style-name');
            if ($basis_stijl === '') {
                $basis_stijl = 'Default';
            }
            $stijl_suffix = $heeft_uren ? 'DurLong' : 'DurShort';
            $data_stijl = $heeft_uren ? 'NdurLong' : 'NdurShort';
            $afgeleide_stijl_sleutel = $basis_stijl . '|' . $stijl_suffix;
            if (!isset($afgeleide_stijlen[$afgeleide_stijl_sleutel])) {
                $afgeleide_stijl_naam = $basis_stijl . $stijl_suffix;
                $afgeleide_stijlen[$afgeleide_stijl_sleutel] = $afgeleide_stijl_naam;

                if (isset($bestaande_stijlen[$basis_stijl])) {
                    $afgeleide_stijl = $bestaande_stijlen[$basis_stijl]->cloneNode(true);
                    if (!($afgeleide_stijl instanceof \DOMElement)) {
                        continue;
                    }
                } else {
                    $afgeleide_stijl = $dom->createElementNS($style_ns, 'style:style');
                    $afgeleide_stijl->setAttributeNS($style_ns, 'style:family', 'table-cell');
                    $afgeleide_stijl->setAttributeNS($style_ns, 'style:parent-style-name', $basis_stijl);
                }

                $afgeleide_stijl->setAttributeNS($style_ns, 'style:name', $afgeleide_stijl_naam);
                $afgeleide_stijl->setAttributeNS($style_ns, 'style:data-style-name', $data_stijl);
                $automatic_styles->appendChild($afgeleide_stijl);
                $bestaande_stijlen[$afgeleide_stijl_naam] = $afgeleide_stijl;
            }

            $cell->setAttribute('table:style-name', $afgeleide_stijlen[$afgeleide_stijl_sleutel]);
            $cell->setAttribute('office:value-type', 'time');
            $cell->removeAttribute('office:value');
            $cell->setAttribute(
                'office:time-value',
                sprintf(
                    'PT%dH%dM%dS',
                    intdiv($duur_seconden, 3600),
                    intdiv($duur_seconden % 3600, 60),
                    $duur_seconden % 60
                )
            );
        }

        return $dom->saveXML();
    }

    /**
     * Zoek in een ODS-tabelrij de cel op basis van 1-based kolomindex.
     *
     * Houdt rekening met table:number-columns-repeated, zodat ook herhaalde
     * lege of uniforme cellen correct naar een logische kolompositie vertaald
     * worden.
     */
    private function get_ods_cell_by_column_index(\DOMElement $row_node, int $column_index): ?\DOMElement
    {
        $current_index = 1;
        foreach ($row_node->childNodes as $child) {
            if (!($child instanceof \DOMElement) || $child->tagName !== 'table:table-cell') {
                continue;
            }
            $repeat = (int)$child->getAttribute('table:number-columns-repeated');
            if ($repeat < 1) {
                $repeat = 1;
            }
            $start = $current_index;
            $end = $current_index + $repeat - 1;
            if ($column_index >= $start && $column_index <= $end) {
                return $child;
            }
            $current_index = $end + 1;
        }
        return null;
    }

    /**
     * @return array{
     *     naam: string,
     *     nummer_ids: list<int>,
     *     iframe_url: string
     * }
     */
    public function get_lijst_metadata(): array
    {
        $this->login();
        try {
            $lijst = $this->factory->create_lijst_uit_request($this->request);
        } catch (GeenLijstException) {
            throw new GebruikersException('Ongeldige lijst');
        }
        $nummer_ids = [];
        foreach ($lijst->get_nummers() as $nummer) {
            $nummer_ids[] = $nummer->get_id();
        }
        return [
            'naam' => $lijst->get_naam(),
            'nummer_ids' => $nummer_ids,
            'iframe_url' => sprintf(
                '%s?lijst=%d',
                $this->config->get()['root_url'],
                $lijst->get_id()
            ),
        ];
    }

    /**
     * @return array{
     *     organisatie: string,
     *     lijsten: list<array{
     *         id: positive-int,
     *         naam: string
     *     }>,
     *     nimbus_url: string,
     *     totaal_aantal_nummers: int
     * }
     */
    public function get_metadata(): array
    {
        $this->login();
        $lijsten = [];
        foreach ($this->factory->get_muzieklijsten() as $lijst) {
            $lijsten[] = [
                'id' => $lijst->get_id(),
                'naam' => $lijst->get_naam(),
            ];
        }
        return [
            'organisatie' => $this->config->get()['organisatie'],
            'lijsten' => $lijsten,
            'nimbus_url' => $this->config->get()['nimbus_url'],
            'totaal_aantal_nummers' => (int)$this->db->selectSingle('SELECT COUNT(*) FROM nummers'),
        ];
    }

    /**
     * @return list<array{
     *     id: positive-int,
     *     tonen: false,
     *     label: string,
     *     verplicht: false
     * }>
     */
    public function get_alle_velden(): array
    {
        $respons = [];
        foreach ($this->factory->get_velden() as $veld) {
            $respons[] = [
                'id' => $veld->get_id(),
                'tonen' => false,
                'label' => $veld->get_label(),
                'verplicht' => false,
            ];
        }
        return $respons;
    }

    /**
     * @return array{
     *     naam: string,
     *     is_actief: bool,
     *     minkeuzes: int,
     *     maxkeuzes: int,
     *     vrijekeuzes: int,
     *     stemmen_per_ip: ?int,
     *     artiest_eenmalig: bool,
     *     mail_stemmers: bool,
     *     random_volgorde: bool,
     *     recaptcha: bool,
     *     email: string,
     *     bedankt_tekst: string,
     *     velden: VeldenData
     * }
     */
    public function get_beheer_lijstdata(): array
    {
        $this->login();
        try {
            $lijst = $this->factory->create_lijst_uit_request($this->request);
        } catch (GeenLijstException) {
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
            'mail_stemmers' => $lijst->is_mail_stemmers(),
            'random_volgorde' => $lijst->is_random_volgorde(),
            'recaptcha' => $lijst->heeft_gebruik_recaptcha(),
            'email' => implode(',', $lijst->get_notificatie_email_adressen()),
            'bedankt_tekst' => $lijst->get_bedankt_tekst(),
            'velden' => $lijst->get_alle_velden_data(),
        ];
    }

    /**
     * Vereist HTTP login voor beheerders
     */
    public function login(): void
    {
        session_start();
        if (array_key_exists('is_ingelogd', $_SESSION) && $_SESSION['is_ingelogd']) {
            return;
        }
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header('WWW-Authenticate: Basic realm="Inloggen"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Je moet inloggen om deze pagina te kunnen zien.';
            exit();
        }
        if (
            $_SERVER['PHP_AUTH_USER'] !== $this->config->get()['php_auth']['user']
            || $_SERVER['PHP_AUTH_PW'] !== $this->config->get()['php_auth']['password']
        ) {
            // header('WWW-Authenticate: Basic realm="Inloggen"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Verkeerd wachtwoord en/of gebruikersnaam. Ververs de pagina met F5 om het nog een keer te proberen.';
            session_destroy();
            throw new GLDException('Verkeerd wachtwoord en/of gebruikersnaam');
        }
        $_SESSION['is_ingelogd'] = true;
    }

    /**
     * Geeft de lijst met vrije keuzes voor de moderatieinterface.
     *
     * Duurt lang want alle nummers worden gecheckt bij OpenAI.
     *
     * Geeft max 50 nummers.
     *
     * De lijst is leeg als de OpenAI API key niet is ingesteld.
     *
     * @return list<ModVrijeKeuzeNummerData>
     */
    public function mod_vrijekeuze_get_nummers(): array
    {
        if ($this->openai_client === null) {
            return [];
        }

        $this->login();
        $this->db->verwijder_ongekoppelde_vrije_keuze_nummers();
        $lijst_id = (int)\filter_var($this->request->lijst_id ?? null, \FILTER_VALIDATE_INT);
        if ($lijst_id === 0) {
            throw new GLDException('Ongeldige lijst_id');
        }
        if (!\is_array($this->request->niet_ids)) {
            throw new GLDException('Ongeldige parameter: niet_ids');
        }
        $niet_ids = \array_map(
            fn($v) => (int)\filter_var($v, \FILTER_VALIDATE_INT),
            $this->request->niet_ids
        );
        $i_niet_ids = \implode(',', $niet_ids);
        $c_niet_ids = \count($niet_ids) === 0 ? '' : "AND n.id NOT IN ({$i_niet_ids})";
        $query = <<<EOT
        SELECT DISTINCT n.id
        FROM nummers n
        INNER JOIN stemmers_nummers sn ON
            n.id = sn.nummer_id
        INNER JOIN stemmers s ON
            s.id = sn.stemmer_id
        AND s.lijst_id = {$lijst_id}
        WHERE
            n.is_vrijekeuze = 1
            {$c_niet_ids}
        ORDER BY n.id
        LIMIT 50
        EOT;
        $nummers = $this->factory->select_objecten(Nummer::class, $query);
        if (\count($nummers) === 0) {
            return [];
        }

        $ai_suggesties = get_ai_suggesties($this->openai_client, $nummers);

        return \array_map(
            fn($nummer) => [
                'id' => $nummer->get_id(),
                'artiest' => $nummer->get_artiest(),
                'titel' => $nummer->get_titel(),
                'ai_suggestie' => $ai_suggesties[$nummer->get_id()],
            ],
            $nummers,
        );
    }

    /**
     * Goedkeuring vrije keuzenummer
     */
    public function mod_vrijekeuze_nummer_opslaan(): void
    {
        $this->login();
        $this->db->disableAutocommit();

        $nummer_id = (int)\filter_var($this->request->id, \FILTER_VALIDATE_INT);
        $artiest = \trim((string)\filter_var($this->request->artiest));
        $titel = \trim((string)\filter_var($this->request->titel));
        $db = (bool)\filter_var($this->request->db, \FILTER_VALIDATE_BOOL);
        $lijst_id = (int)\filter_var($this->request->lijst_id, \FILTER_VALIDATE_INT);

        // Nieuwe titel en artiest kan duplicaten opleveren.
        // Checken en samenvoegen.
        $vgl_artiest = get_vgl_string($artiest, true);
        $vgl_titel = get_vgl_string($titel, false);

        $e_titel = $this->db->escape_string($vgl_titel);
        $e_artiest = $this->db->escape_string($vgl_artiest);
        $duplicaten_query = <<<EOT
        SELECT id
        FROM nummers
        WHERE
            id != {$nummer_id}
            AND vgl_titel = "{$e_titel}"
            AND vgl_artiest = "{$e_artiest}"
        ORDER BY id
        EOT;
        /** @var list<int> $ids */
        $ids = [
            $nummer_id,
            ...$this->db->selectSingleColumn($duplicaten_query),
        ];
        \sort($ids);
        /** @var int $laagste_id */
        $laagste_id = \array_shift($ids);
        nummers_samenvoegen($this->db, $laagste_id, $ids);

        // Flags bijwerken. Houd er rekening mee dat het mogelijk is dat na het
        // samenvoegen van duplicaten er een ander nummer dan het
        // oorspronkelijke nummer wordt bijgewerkt.
        $this->db->updateMulti('nummers', [
            'artiest' => $artiest,
            'titel' => $titel,
            'vgl_artiest' => $vgl_artiest,
            'vgl_titel' => $vgl_titel,
        ], "id = {$laagste_id}");
        $this->db->updateMulti('nummers', [
            'is_vrijekeuze' => $db ? 0 : 2,
        ], "id = {$laagste_id} AND is_vrijekeuze = 1");
        if ($db) {
            try {
                $this->db->insertMulti('lijsten_nummers', [
                    'nummer_id' => $laagste_id,
                    'lijst_id' => $lijst_id,
                ]);
            } catch (SQLDupEntryException) {
            }
        }

        $nummer = $this->factory->create_nummer($laagste_id);
        $nummer->verwijder_ongeldige_stemmen();

        $this->db->commit();
    }

    public function mod_vrijekeuze_nummer_verwijderen(): void
    {
        $this->login();
        $this->db->disableAutocommit();

        // $nummer = $this->factory->create_nummer_uit_request($this->request);
        $nummer_id = (int)\filter_var($this->request->nummer, \FILTER_VALIDATE_INT);
        $query = <<<EOT
        DELETE FROM nummers
        WHERE id = {$nummer_id}
        EOT;
        $this->db->query($query);
        $this->db->verwijder_stemmers_zonder_stemmen();

        $this->db->commit();
    }

    /**
     * Haalt configuratie-informatie op voor de frontend.
     *
     * @return array{
     *    heeft_openai_key: bool,
     *    heeft_recaptcha_key: bool,
     * }
     */
    public function get_config(): array
    {
        return [
            'heeft_openai_key' => $this->openai_client !== null,
            'heeft_recaptcha_key' => $this->config->heeft_recaptcha_keys(),
        ];
    }
}
