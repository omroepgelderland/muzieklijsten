<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

/**
 * Verwerking van AJAX-requests.
 * Alleen functies die rechtstreeks door de frontend mogen worden aangeroepen
 * moeten public zijn.
 *
 * @phpstan-import-type VeldenData from Lijst
 * @phpstan-import-type Resultaten from Lijst
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
     *     recaptcha_sitekey: string,
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
            'organisatie' => $this->config->get_instelling('organisatie'),
            'lijst_naam' => $lijst->get_naam(),
            'heeft_gebruik_recaptcha' => $lijst->heeft_gebruik_recaptcha(),
            'is_actief' => $lijst->is_actief(),
            'velden' => $velden,
            'recaptcha_sitekey' => $this->config->get_instelling('recaptcha', 'sitekey'),
            'privacy_url' => $this->config->get_instelling('privacy_url'),
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
        if ($minkeuzes < 1) {
            throw new GebruikersException('Het minimaal aantal keuzes moet minstens één zijn.');
        }
        if ($maxkeuzes < 1) {
            throw new GebruikersException('Het maximaal aantal keuzes moet minstens één zijn.');
        }
        if ($maxkeuzes < $minkeuzes) {
            throw new GebruikersException('Het maximum aantal keuzes kan niet lager zijn dan het minimum.');
        }
        $vrijekeuzes = min(0, $vrijekeuzes);
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
            if ($titel !== '' && $artiest !== '') {
                $zoekartiest = $this->db->escape_string(strtolower(str_replace(' ', '', $artiest)));
                $zoektitel = $this->db->escape_string(strtolower(str_replace(' ', '', $titel)));
                $sql = <<<EOT
                    SELECT id
                    FROM nummers
                    WHERE
                        LOWER(REPLACE(artiest, " ", "")) = "{$zoekartiest}"
                        AND LOWER(REPLACE(titel, " ", "")) = "{$zoektitel}"
                EOT;
                $res = $this->db->query($sql);
                if ($res->num_rows > 0) {
                    $json['dubbel']++;
                    $nummer_id = (int)$res->fetch_array()[0];
                } else {
                    $json['toegevoegd']++;
                    $nummer_id = $this->db->insertMulti('nummers', [
                        'titel' => $titel,
                        'artiest' => $artiest,
                    ]);
                }
                foreach ($this->request->lijsten as $lijst) {
                    try {
                        $this->db->insertMulti('lijsten_nummers', [
                            'nummer_id' => $nummer_id,
                            'lijst_id' => $lijst,
                        ]);
                        $json['lijsten_nummers']++;
                    } catch (SQLException_DupEntry) {
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
                if (!$nummer->is_vrijekeuze()) {
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
        } catch (MuzieklijstenException $e) {
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
                $this->config->get_instelling('root_url'),
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
            'organisatie' => $this->config->get_instelling('organisatie'),
            'lijsten' => $lijsten,
            'nimbus_url' => $this->config->get_instelling('nimbus_url'),
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
            $_SERVER['PHP_AUTH_USER'] !== $this->config->get_instelling('php_auth', 'user')
            || $_SERVER['PHP_AUTH_PW'] !== $this->config->get_instelling('php_auth', 'password')
        ) {
            // header('WWW-Authenticate: Basic realm="Inloggen"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Verkeerd wachtwoord en/of gebruikersnaam. Ververs de pagina met F5 om het nog een keer te proberen.';
            session_destroy();
            throw new MuzieklijstenException('Verkeerd wachtwoord en/of gebruikersnaam');
        }
        $_SESSION['is_ingelogd'] = true;
    }
}
