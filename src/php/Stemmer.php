<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

class Stemmer {
    
    private int $id;
    private Lijst $lijst;
    private string $ip;
    private \DateTime $tijdstip;
    private bool $is_geanonimiseerd;
    private bool $db_props_set;
    
    /**
     * @param $id ID van het object.
     * @param ?array $data Metadata uit de databasevelden (optioneel).
     */
    public function __construct( int $id, ?array $data = null ) {
        $this->id = $id;
        $this->db_props_set = false;
        if ( isset($data) ) {
            $this->set_data($data);
        }
    }

    /**
     * Geeft de lijst waar deze stemmer op gestemd heeft.
     */
    public function get_lijst(): Lijst {
        $this->set_db_properties();
        return $this->lijst;
    }

    public function is_geanonimiseerd(): bool {
        $this->set_db_properties();
        return $this->is_geanonimiseerd;
    }
    
    public function get_id(): int {
        return $this->id;
    }
    
    /**
     * Geeft aan of twee stemmers dezelfde zijn. Wanneer $obj geen Stemmer is wordt false gegeven.
     * @param $obj Object om deze instantie mee te vergelijken
     * @return bool Of $obj dezelfde stemmer is als deze instantie
     */
    public function equals( mixed $obj ): bool {
        return ( $obj instanceof Stemmer && $obj->get_id() == $this->id );
    }
    
    public function get_ip(): string {
        $this->set_db_properties();
        return $this->ip;
    }
    
    public function get_tijdstip(): \DateTime {
        $this->set_db_properties();
        return $this->tijdstip;
    }
    
    /**
     * Vul het object met velden uit de database.
     */
    private function set_db_properties(): void {
        if ( !$this->db_props_set ) {
            $this->set_data(DB::selectSingleRow(sprintf(
                'SELECT * FROM stemmers WHERE id = %d',
                $this->get_id()
            )));
        }
    }

    /**
     * Plaatst metadata in het object
     * @param array $data Data.
     */
    private function set_data( array $data ): void {
        $this->lijst = new Lijst($data['lijst_id']);
        $this->ip = $data['ip'];
        $this->tijdstip = $data['timestamp'];
        $this->is_geanonimiseerd = (bool)$data['is_geanonimiseerd'];
        $this->db_props_set = true;
    }

    public static function maak(
        Lijst $lijst,
        string $ip
    ): self {
        try {
            $id = DB::insertMulti('stemmers', [
                'lijst_id' => $lijst->get_id(),
                'ip' => $ip
            ]);
            return new self($id);
        } catch ( SQLException_DataTooLong ) {
            throw new GebruikersException('De invoer van een van de tekstvelden is te lang.');
        }
    }

    /**
     * Stem op een nummer toevoegen voor deze stemmer.
     */
    public function add_stem(
        Nummer $nummer,
        string $toelichting,
        bool $is_vrijekeuze = false
    ): StemmerNummer {
        try {
            DB::insertMulti('stemmers_nummers', [
                'nummer_id' => $nummer->get_id(),
                'stemmer_id' => $this->get_id(),
                'toelichting' => $toelichting,
                'is_vrijekeuze' => $is_vrijekeuze
            ]);
            return new StemmerNummer($nummer, $this);
        } catch ( SQLException_DataTooLong ) {
            $max = DB::get_max_kolom_lengte('stemmers_nummers', 'toelichting');
            throw new GebruikersException("De toelichting bij \"{$nummer->get_titel()}\" is te lang. De maximale lengte is {$max} tekens.");
        } catch ( SQLException_DupEntry ) {
            // Bestaande stem teruggeven
            return new StemmerNummer($nummer, $this);
        }
    }
    
    /**
     * Maakt een object uit een id aangeleverd door HTTP POST.
     * @param object $request HTTP-request.
     * @throws Muzieklijsten_Exception
     */
    public static function maak_uit_request( object $request ): self {
        try {
            $id = filter_var($request->stemmer, FILTER_VALIDATE_INT);
        } catch ( UndefinedPropertyException ) {
            throw new Muzieklijsten_Exception('Geen stemmer in invoer');
        }
        if ( $id === false ) {
            throw new Muzieklijsten_Exception(sprintf(
                'Ongeldig stemmer id: %s',
                filter_var($request->stemmer)
            ));
        }
        return new self($id);
    }

    /**
     * Verwijdert de stemmer en al haar stemmen.
     */
    public function verwijderen(): void {
        DB::query("DELETE FROM stemmers WHERE id = {$this->get_id()}");
        $this->db_props_set = false;
    }

    /**
     * Mailt een notificatie van deze stem naar de redactie.
     */
    public function mail_redactie(): void {
        $lijst = $this->get_lijst();
        // Velden
        $velden = [];
        foreach ( $lijst->get_velden() as $veld ) {
            try {
                $velden[] = "{$veld->get_label()}: {$veld->get_stemmer_waarde($this)}";
            } catch ( Muzieklijsten_Exception $e ) {}
        }
        $velden_str = implode("\n", $velden);

        $nummers_lijst = [];
        foreach ( $this->get_stemmen() as $stem ) {
            $nummers_lijst[] = "{$stem->nummer->get_titel()} - {$stem->nummer->get_artiest()}";
            $nummers_lijst[] = "\tToelichting: {$stem->get_toelichting()}";
            $nummers_lijst[] = '';
        }
        $nummers_str = implode("\n", $nummers_lijst);

        $tekst_bericht = <<<EOT
        Ontvangen van:

        {$velden_str}

        {$nummers_str}
        EOT;

        $onderwerp = "Er is gestemd - {$lijst->get_naam()}";

        if ( count($lijst->get_notificatie_email_adressen()) > 0 ) {
            stuur_mail(
                $lijst->get_notificatie_email_adressen(),
                [],
                Config::get_instelling('mail', 'afzender'),
                $onderwerp,
                $tekst_bericht
            );
        }

    }

    /**
     * Mailt een bevestiging naar de stemmer, als dat is geconfigureerd in de
     * lijst en als de stemmer een e-mailadres heeft opgegeven.
     */
    public function mail_stemmer(): void {
        $lijst = $this->get_lijst();
        if ( !$lijst->is_mail_stemmers() ) {
            return;
        }

        $onderwerp = "Je stem voor {$lijst->get_naam()}";
        $naam = 'stemmer';
        foreach ( $lijst->get_velden() as $veld ) {
            if ( $veld->get_label() === 'Naam' ) {
                try {
                    $naam = $veld->get_stemmer_waarde($this);
                } catch ( Muzieklijsten_Exception ) {}
            }
            if ( $veld->get_label() === 'E‑mailadres' || $veld->get_label() === 'E-mailadres' ) {
                try {
                    $email = $veld->get_stemmer_waarde($this);
                } catch ( Muzieklijsten_Exception ) {}
            }
        }
        if ( !isset($email) ) {
            return;
        }

        $html_body = <<<EOT
        <!doctype html>
        <html lang="nl-NL">
        <head>
            <meta charset="utf-8">
            <meta http-equiv="content-type" content="text/html; charset=utf-8">
            <title id="onderwerp"></title>
        </head>
        <body>
            <p>Beste <span id="stemmernaam"></span>,</p>
            <p>Bedankt voor het uitbrengen van je stem op de lijst ‘<span id="lijstnaam"></span>’.</p>
            <p>Dit zijn jouw keuzes:</p>
            <p id="keuzes"></p>
        </body>
        </html>
        EOT;
        $dom = new \DOMDocument();
        $dom->loadHTML($html_body);

        $e_onderwerp = $dom->getElementById('onderwerp');
        $e_onderwerp->appendChild($dom->createTextNode($onderwerp));
        $e_onderwerp->removeAttribute('id');

        $dom->getElementById('stemmernaam')->replaceWith($dom->createTextNode($naam));

        $dom->getElementById('lijstnaam')->replaceWith($dom->createTextNode($lijst->get_naam()));

        $e_keuzes = $dom->getElementById('keuzes');
        $e_keuzes->removeAttribute('id');
        foreach ( $this->get_stemmen() as $stem ) {
            $e_strong = $e_keuzes->appendChild($dom->createElement('strong'));
            $e_strong->appendChild($dom->createTextNode(
                "{$stem->nummer->get_artiest()} – {$stem->nummer->get_titel()}"
            ));
            $toelichting = $stem->get_toelichting();
            if ( $toelichting !== null && $toelichting !== '' ) {
                /** @var \DOMElement $e_span */
                $e_span = $e_keuzes->appendChild($dom->createElement('span'));
                $e_span->setAttribute('style', 'margin-left: 20px; font-style: italic;');
                $e_span->appendChild($dom->createTextNode(
                    " Toelichting: {$stem->get_toelichting()}"
                ));
                $e_keuzes->appendChild($e_span);
            }
            $e_keuzes->appendChild($dom->createElement('br'));
        }

        stuur_mail(
            $email,
            [],
            Config::get_instelling('mail', 'afzender'),
            $onderwerp,
            $dom->textContent,
            $dom->saveHTML()
        );
    }

    /**
     * @return list<StemmerNummer>
     */
    public function get_stemmen(): array {
        $query = <<<EOT
        SELECT *
        FROM stemmers_nummers
        WHERE
            stemmer_id = {$this->get_id()}
        EOT;
        $stemmen = [];
        foreach ( DB::query($query) as $entry ) {
            $stemmen[] = new StemmerNummer(
                new Nummer($entry['nummer_id']),
                $this,
                $entry
            );
        }
        return $stemmen;
    }

    /**
     * Verwijdert alle stemmen van deze stemmer op de lijst.
     */
    public function verwijder_stemmen(): void {
        $query = <<<EOT
        DELETE FROM stemmers_nummers
        WHERE
            stemmer_id = {$this->get_id()}
        EOT;
        DB::query($query);
        verwijder_ongekoppelde_vrije_keuze_nummers();
    }

    /**
     * Verwijdert de inhoud van alle formuliervelden van deze stemmer.
     */
    public function verwijder_velden(): void {
        $query = <<<EOT
        DELETE FROM stemmers_velden
        WHERE
            stemmer_id = {$this->get_id()}
        EOT;
        DB::query($query);
    }

    /**
     * Anonimiseer tekstinvoeren van deze stemmer.
     */
    public function anonimiseer(): void {
        DB::updateMulti(
            'stemmers', [
                'ip' => anonimiseer($this->get_ip()),
                'is_geanonimiseerd' => true
            ],
            "id = {$this->id}"
        );
        $this->db_props_set = false;
        $this->anonimiseer_toelichtingen();
        $this->anonimiseer_velden();
    }

    /**
     * Anonimiseer de toelichtingen die deze stemmer per nummer heeft ingevuld.
     */
    private function anonimiseer_toelichtingen(): void {
        $query = <<<EOT
            SELECT nummer_id, toelichting
            FROM stemmers_nummers
            WHERE
                stemmer_id = {$this->id}
                AND toelichting IS NOT NULL
                AND toelichting != ''
        EOT;
        foreach ( DB::query($query) as ['nummer_id' => $nummer_id, 'toelichting' => $toelichting] ) {
            DB::updateMulti(
                'stemmers_nummers',
                ['toelichting' => anonimiseer($toelichting)],
                "nummer_id = {$nummer_id} AND stemmer_id = {$this->id}"
            );
        }
    }

    /**
     * Anonimiseer de formuliervelden.
     */
    private function anonimiseer_velden(): void {
        $query = <<<EOT
            SELECT veld_id, waarde
            FROM stemmers_velden
            WHERE
                stemmer_id = {$this->id}
                AND waarde IS NOT NULL
                AND waarde != ''
        EOT;
        foreach ( DB::query($query) as ['veld_id' => $veld_id, 'waarde' => $waarde] ) {
            DB::updateMulti(
                'stemmers_velden',
                ['waarde' => anonimiseer($waarde)],
                "veld_id = {$veld_id} AND stemmer_id = {$this->id}"
            );
        }
    }
    
}
