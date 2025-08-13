<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

use gldstdlib\exception\IndexException;

/**
 * @phpstan-type DBData array{
 *     id: positive-int,
 *     actief: positive-int,
 *     naam: string,
 *     minkeuzes: positive-int,
 *     maxkeuzes: positive-int,
 *     vrijekeuzes: positive-int,
 *     stemmen_per_ip: ?positive-int,
 *     artiest_eenmalig: positive-int,
 *     recaptcha: positive-int,
 *     email: ?string,
 *     bedankt_tekst: string,
 *     mail_stemmers: positive-int,
 *     random_volgorde: positive-int
 * }
 * @phpstan-type VeldenData list<array{
 *     id: int,
 *     tonen: bool,
 *     label: string,
 *     verplicht: bool
 * }>
 * @phpstan-type Resultaten list<array{
 *     nummer: array{
 *         id: int,
 *         titel: string,
 *         artiest: string,
 *         is_vrijekeuze: bool
 *     },
 *     stemmen: list<array{
 *         stemmer_id: int,
 *         ip: string,
 *         is_behandeld: bool,
 *         toelichting: string,
 *         timestamp: string,
 *         velden: list<array{
 *             type: string,
 *             waarde: ?string
 *         }>
 *     }>
 * }>
 */
class Lijst
{
    public const VELD_ZICHTBAAR_BIT = 0;
    public const VELD_VERPLICHT_BIT = 1;

    private int $id;
    private bool $actief;
    private string $naam;
    private int $minkeuzes;
    private int $maxkeuzes;
    private int $vrijekeuzes;
    private ?int $stemmen_per_ip;
    private bool $artiest_eenmalig;
    private bool $recaptcha;
    /** @var list<string> */
    private array $notificatie_email_adressen;
    private string $bedankt_tekst;
    private bool $mail_stemmers;
    private bool $random_volgorde;
    /** @var list<Veld> */
    private array $velden;
    /** @var list<Nummer> */
    private array $nummers;
    /** @var list<Stemmer> */
    private array $stemmers;
    private bool $db_props_set;

    /**
     * @param $id ID van het object.
     * @param ?DBData $data Metadata uit de databasevelden (optioneel).
     */
    public function __construct(
        private DB $db,
        private Factory $factory,
        int|string $id,
        ?array $data = null
    ) {
        $this->id = (int)$id;
        $this->db_props_set = false;
        if (isset($data)) {
            $this->set_data($data);
        }
    }

    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Geeft aan of twee muzieklijsten dezelfde zijn. Wanneer $obj geen Muzieklijst is wordt false gegeven.
     *
     * @param $obj Object om deze instantie mee te vergelijken
     *
     * @return bool Of $obj dezelfde muzieklijst is als deze instantie
     */
    public function equals(mixed $obj): bool
    {
        return ( $obj instanceof Lijst && $obj->get_id() == $this->id );
    }

    /**
     * Geeft aan of de lijst actief is.
     */
    public function is_actief(): bool
    {
        $this->set_db_properties();
        return $this->actief;
    }

    public function get_naam(): string
    {
        $this->set_db_properties();
        return $this->naam;
    }

    public function get_minkeuzes(): int
    {
        $this->set_db_properties();
        return $this->minkeuzes;
    }

    public function get_maxkeuzes(): int
    {
        $this->set_db_properties();
        return $this->maxkeuzes;
    }

    /**
     * Geeft het aantal vrije keuzes.
     */
    public function get_vrijekeuzes(): int
    {
        $this->set_db_properties();
        return $this->vrijekeuzes;
    }

    public function get_max_stemmen_per_ip(): ?int
    {
        $this->set_db_properties();
        return $this->stemmen_per_ip;
    }

    public function is_artiest_eenmalig(): bool
    {
        $this->set_db_properties();
        return $this->artiest_eenmalig;
    }

    public function heeft_gebruik_recaptcha(): bool
    {
        $this->set_db_properties();
        return $this->recaptcha;
    }

    /**
     * @return list<string>
     */
    public function get_notificatie_email_adressen(): array
    {
        $this->set_db_properties();
        return $this->notificatie_email_adressen;
    }

    /**
     * Geeft alle velden die bij de lijst horen.
     *
     * @return list<Veld>
     */
    public function get_velden(): array
    {
        if (!isset($this->velden)) {
            $this->velden = [];
            $query = <<<EOT
                SELECT veld_id, verplicht
                FROM lijsten_velden
                WHERE lijst_id = {$this->get_id()}
                ORDER BY veld_id
            EOT;
            foreach ($this->db->query($query) as $entry) {
                $id = (int)$entry['veld_id'];
                $verplicht = (bool)$entry['verplicht'];
                $this->velden[] = $this->factory->create_veld($id, null, $verplicht);
            }
        }
        return $this->velden;
    }

    /**
     * @return VeldenData
     */
    public function get_alle_velden_data(): array
    {
        $respons = [];
        $query = <<<EOT
        SELECT
            v.id,
            v.label,
            !ISNULL(lv.lijst_id) AS tonen,
            IFNULL(lv.verplicht, 0) AS verplicht
        FROM velden v
        LEFT JOIN lijsten_velden lv ON
            lv.lijst_id = {$this->get_id()}
            AND lv.veld_id = v.id
        ORDER BY v.id
        EOT;
        foreach (
            $this->db->query($query) as [
                'id' => $id,
                'label' => $label,
                'tonen' => $tonen,
                'verplicht' => $verplicht,
            ]
        ) {
            $respons[] = [
                'id' => (int)$id,
                'tonen' => (bool)$tonen,
                'label' => $label,
                'verplicht' => (bool)$verplicht,
            ];
        }
        return $respons;
    }

    /**
     * Geeft alle nummers van deze lijst
     *
     * @return list<Nummer>
     */
    public function get_nummers(): array
    {
        if (!isset($this->nummers)) {
            $query = <<<EOT
            SELECT nummer_id AS id
            FROM lijsten_nummers
            WHERE lijst_id = {$this->get_id()}
            EOT;
            $this->nummers = $this->factory->select_objecten(Nummer::class, $query);
        }
        return $this->nummers;
    }

    /**
     * @return list<Stemmer>
     */
    private function get_alle_stemmers(): array
    {
        if (!isset($this->stemmers)) {
            $query = <<<EOT
            SELECT id
            FROM stemmers
            WHERE lijst_id = {$this->id}
            EOT;
            $this->stemmers = $this->factory->select_objecten(Stemmer::class, $query);
        }
        return $this->stemmers;
    }

    /**
     * Geeft de stemmers op deze lijst.
     *
     * @param $van Neem alleen de stemmers die gestemd hebben vanaf deze datum (optioneel).
     * @param $tot Neem alleen de stemmers die gestemd hebben tot en met deze datum (optioneel).
     *
     * @return list<Stemmer>
     */
    public function get_stemmers(?\DateTime $van = null, ?\DateTime $tot = null): array
    {
        if ($van === null && $tot === null) {
            return $this->get_alle_stemmers();
        }

        $where = [];
        if (isset($van)) {
            $where[] = "DATE(timestamp) >= \"{$van->format('Y-m-d')}\"";
        }
        if (isset($tot)) {
            $where[] = "DATE(timestamp) <= \"{$tot->format('Y-m-d')}\"";
        }
        $where_str = implode(' AND ', $where);
        $query = <<<EOT
            SELECT id
            FROM stemmers
            WHERE
                lijst_id = {$this->get_id()}
                AND {$where_str}
        EOT;
        return $this->factory->select_objecten(Stemmer::class, $query);
    }

    /**
     * Geeft alle nummers van deze lijst gesorteerd op titel.
     *
     * @return list<Nummer>
     */
    public function get_nummers_sorteer_titels(): array
    {
        $nummers = [];
        $sql = <<<EOT
            SELECT n.*
            FROM nummers n
            INNER JOIN lijsten_nummers nl ON
                nl.lijst_id = {$this->get_id()}
                AND nl.nummer_id = n.id
            ORDER BY n.titel
        EOT;
        foreach ($this->db->query($sql) as $entry) {
            $nummers[] = $this->factory->create_nummer((int)$entry['id'], $entry);
        }
        return $nummers;
    }

    /**
     * Geeft de stemmen op alle nummers in de lijst.
     *
     * @param $nummer Neem alleen de stemmen op dit nummer mee (optioneel).
     * @param $van Neem alleen de stemmen vanaf deze datum (optioneel).
     * @param $tot Neem alleen de stemmen tot en met deze datum (optioneel).
     *
     * @return list<StemmerNummer> Stemmen
     */
    public function get_stemmen(
        ?Nummer $nummer = null,
        ?\DateTime $van = null,
        ?\DateTime $tot = null
    ): array {
        $where = [];
        if (isset($nummer)) {
            $where[] = "sn.nummer_id = {$nummer->get_id()}";
        }
        $on = [
            "s.lijst_id = {$this->get_id()}",
            's.id = sn.stemmer_id',
        ];
        if (isset($van)) {
            $on[] = "DATE(s.timestamp) >= \"{$van->format('Y-m-d')}\"";
        }
        if (isset($tot)) {
            $on[] = "DATE(s.timestamp) <= \"{$tot->format('Y-m-d')}\"";
        }
        $where_str = implode(' AND ', $where);
        $on_str = implode(' AND ', $on);
        $query = <<<EOT
            SELECT sn.*
            FROM stemmers_nummers sn
            INNER JOIN stemmers s ON {$on_str}
            WHERE
                {$where_str}
        EOT;
        $stemmen = [];
        foreach ($this->db->query($query) as $entry) {
            $stem_nummer = $nummer ?? $this->factory->create_nummer((int)$entry['nummer_id']);
            $stemmen[] = $this->factory->create_stemmer_nummer(
                $stem_nummer,
                $this->factory->create_stemmer((int)$entry['stemmer_id']),
                $entry
            );
        }
        return $stemmen;
    }

    /**
     * Geeft alle nummers met ten minste één stem uit de lijst gesorteerd op het
     * aantal stemmen (hoogste aantal eerst).
     *
     * @param $van Neem alleen de stemmen vanaf deze datum (optioneel).
     * @param $tot Neem alleen de stemmen tot en met deze datum (optioneel).
     *
     * @return list<Nummer>
     */
    public function get_nummers_volgorde_aantal_stemmen(
        ?\DateTime $van = null,
        ?\DateTime $tot = null
    ): array {
        $datumvoorwaarden = [];
        if (isset($van)) {
            $datumvoorwaarden[] = "DATE(s.timestamp) >= \"{$van->format('Y-m-d')}\"";
        }
        if (isset($tot)) {
            $datumvoorwaarden[] = "DATE(s.timestamp) <= \"{$tot->format('Y-m-d')}\"";
        }
        $datumvoorwaarden_str = implode(' AND ', $datumvoorwaarden);
        $query = <<<EOT
            SELECT sn.nummer_id AS id
            FROM stemmers_nummers sn
            INNER JOIN stemmers s ON
                s.lijst_id = {$this->id}
                {$datumvoorwaarden_str}
            GROUP BY sn.nummer_id
            ORDER BY COUNT(sn.stemmer_id) DESC
        EOT;
        return $this->factory->select_objecten(Nummer::class, $query);
    }

    /**
     * Geeft de tekst die stemmers te zien krijgen na het uitbrengen van een stem.
     */
    public function get_bedankt_tekst(): string
    {
        $this->set_db_properties();
        return $this->bedankt_tekst;
    }

    /**
     * Geeft de instelling of stemmers gemaild worden na het stemmen.
     */
    public function is_mail_stemmers(): bool
    {
        $this->set_db_properties();
        return $this->mail_stemmers;
    }

    /**
     * Geeft de instelling voor het random sorteren van nummers in de lijst.
     */
    public function is_random_volgorde(): bool
    {
        $this->set_db_properties();
        return $this->random_volgorde;
    }

    /**
     * Zet alle klassevariabelen terug naar null, behalve het ID. Dit is nuttig
     * wanneer de lijst is verwijderd of zodanig is aangepast dat de
     * klassevariabelen niet meer overeenkomen met de database, en dus opnieuw
     * moeten worden opgehaald.
     * Wanneer er na de reset functies worden aangeroepen kunnen er twee dingen
     * gebeuren:
     * 1. Data wordt opnieuw opgehaald en is consistent met de database
     * 2. Bij het ophalen van data wordt geconstateerd dat de lijst niet meer
     *    bestaat en een foutmelding gegeven.
     */
    public function reset(): void
    {
        $this->db_props_set = false;
        unset($this->velden);
        unset($this->nummers);
        unset($this->stemmers);
    }

    /**
     * Verwijdert de lijst. Koppelingen met nummers, velden, stemmen e.d. worden ook verwijderd
     */
    public function remove(): void
    {
        $this->db->query(sprintf(
            'DELETE FROM lijsten WHERE id = %d',
            $this->get_id()
        ));
        $this->reset();
    }

    /**
     * Vul het object met velden uit de database.
     */
    private function set_db_properties(): void
    {
        if (!$this->db_props_set) {
            $this->set_data($this->db->selectSingleRow(sprintf(
                'SELECT * FROM lijsten WHERE id = %d',
                $this->get_id()
            )));
        }
    }

    /**
     * Plaatst metadata in het object
     *
     * @param DBData $data Data.
     */
    private function set_data(array $data): void
    {
        $this->actief = (bool)$data['actief'];
        $this->naam = $data['naam'];
        $this->minkeuzes = (int)$data['minkeuzes'];
        $this->maxkeuzes = (int)$data['maxkeuzes'];
        $this->vrijekeuzes = (int)$data['vrijekeuzes'];
        $this->stemmen_per_ip = isset($data['stemmen_per_ip'])
            ? (int)$data['stemmen_per_ip']
            : $data['stemmen_per_ip'];
        $this->artiest_eenmalig = (bool)$data['artiest_eenmalig'];
        $this->recaptcha = (bool)$data['recaptcha'];
        $this->notificatie_email_adressen = [];
        foreach (explode(',', $data['email']) as $adres) {
            $adres = trim($adres);
            if (strlen($adres) > 0) {
                $this->notificatie_email_adressen[] = $adres;
            }
        }
        $this->bedankt_tekst = $data['bedankt_tekst'];
        $this->mail_stemmers = (bool)$data['mail_stemmers'];
        $this->random_volgorde = (bool)$data['random_volgorde'];
        $this->db_props_set = true;
    }

    /**
     * Maakt een nieuwe inactieve lijst met dezelfde eigenschappen als deze en
     * verplaatst alle stemmen op deze lijst daar naartoe.
     *
     * @param $naam Naam van de nieuwe lijst.
     */
    public function dupliceer(string $naam): Lijst
    {
        // Nieuwe lijst maken.
        $e_naam = $this->db->escape_string($naam);
        $query = <<<EOT
            INSERT INTO lijsten
                (
                    actief,
                    naam,
                    minkeuzes,
                    maxkeuzes,
                    stemmen_per_ip,
                    artiest_eenmalig,
                    recaptcha,
                    email,
                    bedankt_tekst,
                    mail_stemmers,
                    random_volgorde
                )
                SELECT
                    0,
                    "{$e_naam}",
                    minkeuzes,
                    maxkeuzes,
                    stemmen_per_ip,
                    artiest_eenmalig,
                    recaptcha,
                    email,
                    bedankt_tekst,
                    mail_stemmers,
                    random_volgorde
                FROM lijsten
                WHERE id = {$this->get_id()}
        EOT;
        $this->db->query($query);
        $nieuw_id = $this->db->getDB()->insert_id;

        // Velden koppelen
        $query = <<<EOT
            INSERT INTO lijsten_velden
                (lijst_id, veld_id, verplicht)
                SELECT
                    {$nieuw_id},
                    veld_id,
                    verplicht
                FROM lijsten_velden
                WHERE lijst_id = {$this->get_id()}
        EOT;
        $this->db->query($query);

        // Nummers koppelen
        $query = <<<EOT
            INSERT INTO lijsten_nummers
                (nummer_id, lijst_id)
                SELECT nummer_id, {$nieuw_id}
                FROM lijsten_nummers
                WHERE lijst_id = {$this->get_id()}
        EOT;
        $this->db->query($query);

        // Stemmen verplaatsen
        $query = <<<EOT
            UPDATE stemmers
            SET
                lijst_id = {$nieuw_id}
            WHERE
                lijst_id = {$this->get_id()}
        EOT;
        $this->db->query($query);
        $this->stemmers = [];

        return $this->factory->create_lijst($nieuw_id);
    }

    public function is_max_stemmen_per_ip_bereikt(): bool
    {
        if ($this->get_max_stemmen_per_ip() === null) {
            return false;
        }
        $query = <<<EOT
            SELECT
                COUNT(s.id) >= {$this->get_max_stemmen_per_ip()}
            FROM stemmers s
            WHERE
                s.lijst_id = {$this->id}
                AND s.ip = "{$_SERVER['REMOTE_ADDR']}"
        EOT;
        return (bool)$this->db->selectSingle($query);
    }

    /**
     * Voegt een nummer toe aan de stemlijst.
     *
     * @param $nummer
     */
    public function nummer_toevoegen(Nummer $nummer): void
    {
        $this->db->insertMulti('lijsten_nummers', [
            'nummer_id' => $nummer->get_id(),
            'lijst_id' => $this->get_id(),
        ]);
        if (isset($this->nummers)) {
            $this->nummers[] = $nummer;
        }
    }

    /**
     * Haalt een nummer weg uit de stemlijst.
     * Het nummer blijft bestaan in de database; het is alleen niet meer aan
     * deze lijst gekoppeld.
     * Alle stemmen op dit nummer in deze lijst worden verwijderd.
     * Stemmers die geen stemmen meer hebben worden verwijderd.
     *
     * @param $nummer
     */
    public function verwijder_nummer(Nummer $nummer): void
    {
        // Verwijder de koppeling.
        $query = <<<EOT
        DELETE
        FROM lijsten_nummers
        WHERE
            nummer_id = {$nummer->get_id()}
            AND lijst_id = {$this->id}
        EOT;
        $this->db->query($query);

        // Verwijder stemmen en stemmers
        $query = <<<EOT
        DELETE s, sn
        FROM stemmers_nummers sn
        INNER JOIN stemmen s ON
            s.id = sn.stemmer_id
            AND s.lijst_id = {$this->id}
        WHERE
            sn.nummer_id = {$nummer->get_id()}
        EOT;

        $this->db->verwijder_ongekoppelde_vrije_keuze_nummers();
        $this->db->verwijder_stemmers_zonder_stemmen();
    }

    /**
     * Verwijdert de lijst.
     */
    public function verwijderen(): void
    {
        $this->db->query("DELETE FROM lijsten WHERE id = {$this->get_id()}");
        $this->db->verwijder_stemmers_zonder_stemmen();
        $this->reset();
    }

    // /**
    //  * Maakt HTML voor een invoerveld in het formulier.
    //  * @param $id ID en naam van het veld.
    //  * @param $label Zichtbaar label.
    //  * @param $type Type van <input>
    //  * @param $leeg_feedback Tekst die aan de gebruiker wordt getoond
    //  * als het verplichte veld niet is ingevuld.
    //  * @param $is_verplicht Of het veld verplicht is.
    //  * @param $max_length Maximale lengte van tekstinvoer (optioneel).
    //  * @param $placeholder Placeholdertekst (optioneel).
    //  * @return string HTML.
    //  */
    // private function get_formulier_html(
    //     string $id,
    //     string $label,
    //     string $type,
    //     string $leeg_feedback,
    //     bool $is_verplicht,
    //     ?int $max_length = null,
    //     string $placeholder = ''
    // ): string {
    //     $template = <<<EOT
    //     <div class="form-group row">
    //         <label class="control-label col-sm-2"></label>
    //         <div class="col-sm-10">
    //             <input class="form-control">
    //         </div>
    //     </div>
    //     EOT;
    //     $doc = new HTMLTemplate($template);
    //     /** @var \DOMElement $e_label */
    //     $e_label = $doc->getElementsByTagName('label')->item(0);
    //     /** @var \DOMElement $e_input */
    //     $e_input = $doc->getElementsByTagName('input')->item(0);
    //     $e_label->appendChild($doc->createTextNode($label));
    //     $e_label->setAttribute('for', $id);
    //     $e_input->setAttribute('type', $type);
    //     $e_input->setAttribute('id', $id);
    //     $e_input->setAttribute('name', $id);
    //     if ( strlen($placeholder) > 0 ){
    //         $e_input->setAttribute('placeholder', $placeholder);
    //     }
    //     $e_input->setAttribute('data-leeg-feedback', $leeg_feedback);
    //     if ( isset($max_length) && in_array($type, ['text', 'search', 'url', 'tel', 'email', 'password']) ) {
    //         $e_input->setAttribute('maxlength', $max_length);
    //     }
    //     if ( $is_verplicht ) {
    //         $e_input->appendChild($doc->createAttribute('required'));
    //     }
    //     return $doc->saveHTML();
    // }

    public function set_veld(Veld $veld, bool $verplicht): void
    {
        $this->db->insert_update_multi('lijsten_velden', [
            'lijst_id' => $this->get_id(),
            'veld_id' => $veld->get_id(),
            'verplicht' => $verplicht,
        ]);
    }

    public function remove_veld(Veld $veld): void
    {
        $query = <<<EOT
        DELETE
        FROM lijsten_velden
        WHERE
            lijst_id = {$this->get_id()}
            AND veld_id = {$veld->get_id()}
        EOT;
        $this->db->query($query);
    }

    /**
     * @return Resultaten
     */
    public function get_resultaten(): array
    {
        $query = <<<EOT
        SELECT
            n.id as nummer_id,
            sn.is_vrijekeuze,
            n.titel,
            n.artiest,
            s.id AS stemmer_id,
            s.ip,
            sn.behandeld,
            sn.toelichting,
            s.timestamp,
            s.is_geanonimiseerd,
            v.id AS veld_id,
            v.type,
            sv.waarde
        FROM stemmers_nummers sn
        INNER JOIN nummers n ON
            n.id = sn.nummer_id
        INNER JOIN stemmers s ON
            s.id = sn.stemmer_id
            AND s.lijst_id = {$this->get_id()}
        LEFT JOIN lijsten_velden lv ON
            lv.lijst_id = {$this->get_id()}
        LEFT JOIN velden v ON
            v.id = lv.veld_id
        LEFT JOIN stemmers_velden sv ON
            sv.stemmer_id = s.id
            AND sv.veld_id = v.id
        INNER JOIN (
            SELECT
                nummer_id,
                COUNT(nummer_id) AS aantal
            FROM stemmers_nummers
            WHERE
                stemmer_id IN (
                    SELECT id
                    FROM stemmers
                    WHERE
                        lijst_id = {$this->get_id()}
                )
            GROUP BY nummer_id
        ) a ON
            a.nummer_id = n.id
        ORDER BY
            a.aantal DESC,
            n.id,
            s.timestamp ASC,
            s.id,
            v.id,
            RAND()
        EOT;
        $nummers = [];
        foreach (
            $this->db->query($query) as [
                'nummer_id' => $nummer_id,
                'is_vrijekeuze' => $is_vrijekeuze,
                'titel' => $titel,
                'artiest' => $artiest,
                'stemmer_id' => $stemmer_id,
                'ip' => $ip,
                'behandeld' => $is_behandeld,
                'toelichting' => $toelichting,
                'timestamp' => $timestamp,
                'is_geanonimiseerd' => $is_geanonimiseerd,
                'veld_id' => $veld_id,
                'type' => $type,
                'waarde' => $waarde,
            ]
        ) {
            $nummer_id = (int)$nummer_id;
            $is_vrijekeuze = (bool)$is_vrijekeuze;
            $stemmer_id = (int)$stemmer_id;
            $is_behandeld = (bool)$is_behandeld;
            $is_geanonimiseerd = (bool)$is_geanonimiseerd;
            $veld_id = (int)$veld_id;
            if ($is_geanonimiseerd) {
                $ip = $toelichting = $waarde = '🔒';
                $type = 'text';
            }

            $nummers[$nummer_id]['nummer'] = [
                'id' => $nummer_id,
                'titel' => $titel,
                'artiest' => $artiest,
                'is_vrijekeuze' => $is_vrijekeuze,
            ];
            $nummers[$nummer_id]['stemmen'][$stemmer_id]['stemmer_id'] = $stemmer_id;
            $nummers[$nummer_id]['stemmen'][$stemmer_id]['ip'] = $ip;
            $nummers[$nummer_id]['stemmen'][$stemmer_id]['is_behandeld'] = $is_behandeld;
            $nummers[$nummer_id]['stemmen'][$stemmer_id]['toelichting'] = $toelichting;
            $nummers[$nummer_id]['stemmen'][$stemmer_id]['timestamp'] = $timestamp;
            // $nummers[$nummer_id]['stemmen'][$stemmer_id]['is_geanonimiseerd'] = $is_geanonimiseerd;
            $nummers[$nummer_id]['stemmen'][$stemmer_id]['velden'][$veld_id] = [
                'type' => $type,
                'waarde' => $waarde,
            ];
        }
        foreach ($nummers as $nummer_id => $nummer) {
            foreach ($nummers[$nummer_id]['stemmen'] as $stemmer_id => $stemmer) {
                $nummers[$nummer_id]['stemmen'][$stemmer_id]['velden'] = array_values($stemmer['velden']);
            }
            $nummers[$nummer_id]['stemmen'] = array_values($nummers[$nummer_id]['stemmen']);
        }
        return array_values($nummers);
    }

    /**
     * Zoekt een stemmer op deze lijst aan de hand van een e-mailadres.
     *
     * @return Stemmer|null De stemmer, of null als die niet kan  worden
     * gevonden.
     */
    public function get_stemmer_uit_email(string $email): ?Stemmer
    {
        $e_email = $this->db->escape_string(
            \strtolower(
                \filter_var($email, \FILTER_SANITIZE_EMAIL)
            )
        );
        $query = <<<EOT
        SELECT sn.stemmer_id AS id
        FROM stemmers_nummers sn
        INNER JOIN stemmers s ON
            s.id = sn.stemmer_id
            AND s.lijst_id = {$this->get_id()}
        INNER JOIN stemmers_velden sv ON
            sv.stemmer_id = sn.stemmer_id
            AND sv.veld_id = 6
            AND sv.waarde = "{$e_email}"
        EOT;
        try {
            return $this->factory->select_objecten(Stemmer::class, $query)[0];
        } catch (IndexException) {
            return null;
        }
    }

    /**
     * Zoekt een stemmer op deze lijst aan de hand van een telefoonnummer.
     *
     * @return Stemmer|null De stemmer, of null als die niet kan  worden
     * gevonden.
     */
    public function get_stemmer_uit_telefoonnummer(string $telefoonnummer): ?Stemmer
    {
        $e_telefoonnummer = filter_telefoonnummer($telefoonnummer);
        $query = <<<EOT
        SELECT sn.stemmer_id AS id
        FROM stemmers_nummers sn
        INNER JOIN stemmers s ON
            s.id = sn.stemmer_id
            AND s.lijst_id = {$this->get_id()}
        INNER JOIN stemmers_velden sv ON
            sv.stemmer_id = sn.stemmer_id
            AND sv.veld_id = 5
            AND sv.waarde = "{$e_telefoonnummer}"
        EOT;
        try {
            return $this->factory->select_objecten(Stemmer::class, $query)[0];
        } catch (IndexException) {
            return null;
        }
    }
}
