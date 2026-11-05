<?php

declare(strict_types=1);

namespace muzieklijsten;

use gldstdlib\exception\GLDException;
use gldstdlib\exception\SQLDataTooLongException;
use gldstdlib\exception\SQLDupEntryException;

use function gldstdlib\ns;
use function gldstdlib\send_mail;

/**
 * @phpstan-type StemmerData array{
 *     lijst: Lijst,
 *     ip: string,
 *     tijdstip: \DateTimeImmutable,
 *     is_geanonimiseerd: bool,
 * }
 */
class Stemmer
{
    /** @var ?StemmerData */
    private ?array $data = null;

    /**
     * @param $id ID van de stemmer.
     */
    public function __construct(
        private DB $db,
        private Config $config,
        private Factory $factory,
        public readonly int $id,
    ) {
    }

    /**
     * Geeft de lijst waar deze stemmer op gestemd heeft.
     */
    public function get_lijst(): Lijst
    {
        return $this->get_data()['lijst'];
    }

    public function is_geanonimiseerd(): bool
    {
        return $this->get_data()['is_geanonimiseerd'];
    }

    public function get_ip(): string
    {
        return $this->get_data()['ip'];
    }

    public function get_tijdstip(): \DateTimeImmutable
    {
        return $this->get_data()['tijdstip'];
    }

    /**
     * @return StemmerData
     */
    private function get_data(): array
    {
        return $this->data ?? $this->set_db_data(
            $this->db->selectSingleRow(<<<SQL
            SELECT *
            FROM stemmers
            WHERE id = {$this->id}
            SQL),
        );
    }

    /**
     * @param StemmerData $data De nieuwe data voor de stemmer.
     */
    public function set_data(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @param array<string, string|null> $raw_db_data
     *
     * @return StemmerData
     */
    public function set_db_data(array $raw_db_data): array
    {
        return $this->data = [
            'lijst' => $this->factory->create_lijst((int)$raw_db_data['lijst_id']),
            'ip' => ns($raw_db_data['ip']),
            'tijdstip' => dbDateTimeToDateTimeImmutable(ns($raw_db_data['timestamp'])),
            'is_geanonimiseerd' => (bool)$raw_db_data['is_geanonimiseerd'],
        ];
    }

    /**
     * Stem op een nummer toevoegen voor deze stemmer.
     */
    public function add_stem(
        Nummer $nummer,
        string $toelichting,
        bool $is_vrijekeuze = false,
    ): StemmerNummer {
        try {
            $this->db->insertMulti('stemmers_nummers', [
                'nummer_id' => $nummer->id,
                'stemmer_id' => $this->id,
                'toelichting' => $toelichting,
                'is_vrijekeuze' => $is_vrijekeuze,
            ]);
            return $this->factory->create_stemmer_nummer($nummer, $this);
        } catch (SQLDataTooLongException) {
            $max = $this->db->get_max_kolom_lengte('stemmers_nummers', 'toelichting');
            throw new GebruikersException(
                "De toelichting bij \"{$nummer->get_titel()}\" is te lang. De maximale lengte is {$max} tekens.",
            );
        } catch (SQLDupEntryException) {
            // Bestaande stem teruggeven
            return $this->factory->create_stemmer_nummer($nummer, $this);
        }
    }

    /**
     * Verwijdert de stemmer en al haar stemmen.
     */
    public function verwijderen(): void
    {
        $this->db->execute("DELETE FROM stemmers WHERE id = {$this->id}");
        $this->data = null;
    }

    /**
     * Mailt een notificatie van deze stem naar de redactie.
     */
    public function mail_redactie(): void
    {
        $lijst = $this->get_lijst();

        $onderwerp = "Er is gestemd - {$lijst->get_naam()}";

        $template = <<<'HTML'
        <html lang="nl-NL">
        <head>
            <meta charset="utf-8">
            <meta http-equiv="content-type" content="text/html; charset=utf-8">
            <title></title>
        </head>
        <body>
            <h2>Ontvangen van:</h2>
            <table>
                <tbody id="velden"></tbody>
            </table>
            <h2>Nummers:</h2>
            <table>
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Artiest</th>
                        <th>Toelichting</th>
                    </tr>
                </thead>
                <tbody id="nummers"></tbody>
            </table>
        </body>
        </html>
        HTML;

        $dom = new \DOMDocument();
        $dom->loadHTML($template);

        $e_title = ns($dom->getElementsByTagName('title')->item(0));
        $e_title->nodeValue = $onderwerp;

        // Velden
        $e_velden = ns($dom->getElementById('velden'));
        foreach ($lijst->get_velden() as $veld) {
            try {
                $label = $veld->get_label();
                $waarde = $veld->get_stemmer_waarde($this);
            } catch (GLDException) {
                continue;
            }
            $e_tr = $dom->createElement('tr');
            $e_velden->appendChild($e_tr);

            $e_label = $dom->createElement('td');
            $e_tr->appendChild($e_label);
            $e_label->appendChild($dom->createTextNode($label));

            $e_waarde = $dom->createElement('td');
            $e_tr->appendChild($e_waarde);
            $e_waarde->appendChild($dom->createTextNode($waarde));
        }

        // Nummers
        $e_nummers = ns($dom->getElementById('nummers'));
        foreach ($this->get_stemmen() as $stem) {
            $e_tr = $dom->createElement('tr');
            $e_nummers->appendChild($e_tr);

            $e_titel = $dom->createElement('td');
            $e_tr->appendChild($e_titel);
            $e_titel->appendChild($dom->createTextNode($stem->nummer->get_titel()));

            $e_artiest = $dom->createElement('td');
            $e_tr->appendChild($e_artiest);
            $e_artiest->appendChild($dom->createTextNode($stem->nummer->get_artiest()));

            $e_toelichting = $dom->createElement('td');
            $e_tr->appendChild($e_toelichting);
            $e_toelichting->appendChild($dom->createTextNode($stem->get_toelichting() ?? ''));
        }

        if (\count($lijst->get_notificatie_email_adressen()) > 0) {
            send_mail(
                to: $lijst->get_notificatie_email_adressen(),
                from: $this->config->get_afzender_mail_adres(),
                subject: $onderwerp,
                html_message: $dom,
            );
        }
    }

    /**
     * Mailt een bevestiging naar de stemmer, als dat is geconfigureerd in de
     * lijst en als de stemmer een e-mailadres heeft opgegeven.
     */
    public function mail_stemmer(): void
    {
        $lijst = $this->get_lijst();
        if (!$lijst->is_mail_stemmers()) {
            return;
        }

        $onderwerp = "Je stem voor {$lijst->get_naam()}";
        $naam = 'stemmer';
        foreach ($lijst->get_velden() as $veld) {
            if ($veld->get_label() === 'Naam') {
                try {
                    $naam = $veld->get_stemmer_waarde($this);
                } catch (GLDException) {
                }
            }
            if ($veld->get_label() === 'E‑mailadres' || $veld->get_label() === 'E-mailadres') {
                try {
                    $email = $veld->get_stemmer_waarde($this);
                } catch (GLDException) {
                }
            }
        }
        if (!isset($email)) {
            return;
        }

        $html_body = <<<'HTML'
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
        HTML;
        $dom = new \DOMDocument();
        $dom->loadHTML($html_body);

        $e_onderwerp = ns($dom->getElementById('onderwerp'));
        $e_onderwerp->appendChild($dom->createTextNode($onderwerp));
        $e_onderwerp->removeAttribute('id');

        ns($dom->getElementById('stemmernaam'))->replaceWith($dom->createTextNode($naam));

        ns($dom->getElementById('lijstnaam'))->replaceWith($dom->createTextNode($lijst->get_naam()));

        $e_keuzes = ns($dom->getElementById('keuzes'));
        $e_keuzes->removeAttribute('id');
        foreach ($this->get_stemmen() as $stem) {
            $e_strong = $e_keuzes->appendChild($dom->createElement('strong'));
            $e_strong->appendChild($dom->createTextNode(
                "{$stem->nummer->get_artiest()} – {$stem->nummer->get_titel()}",
            ));
            $toelichting = $stem->get_toelichting();
            if ($toelichting !== null && $toelichting !== '') {
                /** @var \DOMElement $e_span */
                $e_span = $e_keuzes->appendChild($dom->createElement('span'));
                $e_span->setAttribute('style', 'margin-left: 20px; font-style: italic;');
                $e_span->appendChild($dom->createTextNode(
                    " Toelichting: {$stem->get_toelichting()}",
                ));
                $e_keuzes->appendChild($e_span);
            }
            $e_keuzes->appendChild($dom->createElement('br'));
        }

        send_mail(
            to: $email,
            from: $this->config->get_afzender_mail_adres(),
            subject: $onderwerp,
            html_message: $dom,
        );
    }

    /**
     * @return list<StemmerNummer>
     */
    public function get_stemmen(): array
    {
        $query = <<<SQL
        SELECT *
        FROM stemmers_nummers
        WHERE
            stemmer_id = {$this->id}
        SQL;
        $stemmen = [];
        foreach ($this->db->query($query) as $entry) {
            $stem = $this->factory->create_stemmer_nummer(
                $this->factory->create_nummer((int)$entry['nummer_id']),
                $this,
            );
            $stem->set_db_data($entry);
            $stemmen[] = $stem;
        }
        return $stemmen;
    }

    /**
     * Verwijdert alle stemmen van deze stemmer op de lijst.
     */
    public function verwijder_stemmen(): void
    {
        $query = <<<SQL
        DELETE FROM stemmers_nummers
        WHERE
            stemmer_id = {$this->id}
        SQL;
        $this->db->execute($query);
        $this->db->verwijder_ongekoppelde_vrije_keuze_nummers();
    }

    /**
     * Verwijdert de inhoud van alle formuliervelden van deze stemmer.
     */
    public function verwijder_velden(): void
    {
        $query = <<<SQL
        DELETE FROM stemmers_velden
        WHERE
            stemmer_id = {$this->id}
        SQL;
        $this->db->execute($query);
    }

    /**
     * Anonimiseer tekstinvoeren van deze stemmer.
     */
    public function anonimiseer(): void
    {
        $this->db->updateMulti(
            'stemmers',
            [
                'ip' => anonimiseer($this->get_ip()),
                'is_geanonimiseerd' => true,
            ],
            "id = {$this->id}",
        );
        $this->data = null;
        $this->anonimiseer_toelichtingen();
        $this->anonimiseer_velden();
    }

    /**
     * Anonimiseer de toelichtingen die deze stemmer per nummer heeft ingevuld.
     */
    private function anonimiseer_toelichtingen(): void
    {
        $query = <<<SQL
        SELECT nummer_id, toelichting
        FROM stemmers_nummers
        WHERE
            stemmer_id = {$this->id}
            AND toelichting IS NOT NULL
            AND toelichting != ''
        SQL;
        foreach ($this->db->query($query) as ['nummer_id' => $nummer_id, 'toelichting' => $toelichting]) {
            $nummer_id = (int)$nummer_id;
            $this->db->updateMulti(
                'stemmers_nummers',
                ['toelichting' => anonimiseer($toelichting)],
                "nummer_id = {$nummer_id} AND stemmer_id = {$this->id}",
            );
        }
    }

    /**
     * Anonimiseer de formuliervelden.
     */
    private function anonimiseer_velden(): void
    {
        $query = <<<SQL
        SELECT veld_id, waarde
        FROM stemmers_velden
        WHERE
            stemmer_id = {$this->id}
            AND waarde IS NOT NULL
            AND waarde != ''
        SQL;
        foreach ($this->db->query($query) as ['veld_id' => $veld_id, 'waarde' => $waarde]) {
            $veld_id = (int)$veld_id;
            $this->db->updateMulti(
                'stemmers_velden',
                ['waarde' => anonimiseer($waarde)],
                "veld_id = {$veld_id} AND stemmer_id = {$this->id}",
            );
        }
    }

    /**
     * Verwijdert alle stemmen die niet voldoen aan de restricties van de
     * stemlijst.
     */
    public function verwijder_ongeldige_stemmen(): void
    {
        $this->verwijder_dubbele_artiesten_stemmen();

        $query = <<<SQL
        SELECT is_vrijekeuze, COUNT(nummer_id) AS aantal
        FROM stemmers_nummers
        WHERE
            stemmer_id = {$this->id}
        GROUP BY is_vrijekeuze
        SQL;
        $aantal_vrije_keuzes = 0;
        $aantal_niet_vrije_keuzes = 0;
        foreach (
            $this->db->query($query) as [
                'is_vrijekeuze' => $is_vrijekeuze,
                'aantal' => $aantal,
            ]
        ) {
            if ((bool)$is_vrijekeuze) {
                $aantal_vrije_keuzes = (int)$aantal;
            } else {
                $aantal_niet_vrije_keuzes = (int)$aantal;
            }
        }
        $overschot_vrije_keuzes = $aantal_vrije_keuzes - $this->get_lijst()->get_vrijekeuzes();
        $overschot_niet_vrije_keuzes = $aantal_niet_vrije_keuzes - $this->get_lijst()->get_maxkeuzes();

        // Verwijder overschot aan niet-vrije keuzes
        if ($overschot_niet_vrije_keuzes > 0) {
            $query = <<<SQL
            DELETE
            FROM stemmers_nummers
            WHERE
                stemmer_id = {$this->id}
                AND is_vrijekeuze = 0
            ORDER BY RAND()
            LIMIT {$overschot_niet_vrije_keuzes}
            SQL;
            $this->db->execute($query);
        }
        // Verwijder overschot aan vrije keuzes
        if ($overschot_vrije_keuzes > 0) {
            $query = <<<SQL
            DELETE
            FROM stemmers_nummers
            WHERE
                stemmer_id = {$this->id}
                AND is_vrijekeuze = 1
            ORDER BY RAND()
            LIMIT {$overschot_vrije_keuzes}
            SQL;
            $this->db->execute($query);
        }
    }

    /**
     * Verwijdert stemmen op verschillende nummers van dezelfde artiest.
     *
     * Dit gebeurt alleen wanneer er in de stemlijst is ingesteld dat er niet
     * dubbel op dezelfde artiest mag worden gestemd.
     *
     * Van de dubbelingen wordt één stem behouden.
     */
    private function verwijder_dubbele_artiesten_stemmen(): void
    {
        if (!$this->get_lijst()->is_artiest_eenmalig()) {
            return;
        }

        $query = <<<SQL
        SELECT
            GROUP_CONCAT(n.id)
        FROM stemmers_nummers sn
        INNER JOIN nummers n ON
            n.id = sn.nummer_id
        WHERE
            sn.stemmer_id = {$this->id}
        GROUP BY n.artiest
        HAVING
            COUNT(n.id) > 1
        SQL;
        $verwijder_stemmen_nummer_ids = [];
        foreach ($this->db->selectSingleColumn($query) as $idlijst) {
            $ids = \array_map(static fn($id) => (int)$id, \explode(',', $idlijst ?? ''));
            \shuffle($ids);
            \array_shift($ids); // Behoud random nummer
            $verwijder_stemmen_nummer_ids = \array_merge($verwijder_stemmen_nummer_ids, $ids);
        }
        if (\count($verwijder_stemmen_nummer_ids) > 0) {
            $i_ids = \implode(',', $verwijder_stemmen_nummer_ids);
            $query = <<<SQL
            DELETE
            FROM stemmers_nummers
            WHERE
                stemmer_id = {$this->id}
                AND nummer_id IN ({$i_ids})
            SQL;
            $this->db->execute($query);
        }
    }
}
