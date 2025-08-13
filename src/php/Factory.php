<?php

namespace muzieklijsten;

use DI\FactoryInterface;
use gldstdlib\exception\GLDException;
use gldstdlib\exception\SQLDataTooLongException;
use gldstdlib\exception\SQLException;
use gldstdlib\exception\UndefinedPropertyException;

/**
 * @phpstan-import-type DBData from Lijst as LijstDBData
 * @phpstan-import-type DBData from Nummer as NummerDBData
 * @phpstan-import-type DBData from Stemmer as StemmerDBData
 * @phpstan-import-type DBData from StemmerNummer as StemmerNummerDBData
 * @phpstan-import-type DBData from Veld as VeldDBData
 */
class Factory
{
    public function __construct(
        private FactoryInterface $container,
        private DB $db,
    ) {
    }

    /**
     * Maakt een instantie van een object aan de hand van een databasequery.
     *
     * @template T of object
     *
     * @param class-string<T> $class Type object.
     * @param $query Databasequery. De namen van de kolommen in het
     * SELECT-statement moeten overeenkomen met de namen van de
     * parameterargumenten van de constructor van de class.
     *
     * @return T
     *
     * @throws SQLException Als er geen resultaat is.
     * @throws SQLException Als de query mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de
     * database.
     */
    public function select_object(string $class, string $query): object
    {
        return $this->container->make(
            $class,
            $this->db->selectSingleRow($query)
        );
    }

    /**
     * Maakt een lijst met instanties van objecten aan de hand van een
     * databasequery.
     *
     * @template T of object
     *
     * @param class-string<T> $class Type object.
     * @param $query Databasequery. De namen van de kolommen in het
     * SELECT-statement moeten overeenkomen met de namen van de
     * parameterargumenten van de constructor van de class.
     *
     * @return list<T>
     */
    public function select_objecten(string $class, string $query): array
    {
        $lijst = [];
        foreach ($this->db->query($query) as $item) {
            $lijst[] = $this->container->make(
                $class,
                $item
            );
        }
        return $lijst;
    }

    /**
     * @param object $request Requestdata van de frontend.
     */
    public function create_ajax(
        object $request,
    ): Ajax {
        return $this->container->make(
            Ajax::class,
            ['request' => $request]
        );
    }

    /**
     * @param $id ID van de lijst.
     * @param ?LijstDBData $data Metadata uit de databasevelden (optioneel).
     */
    public function create_lijst(
        int $id,
        ?array $data = null,
    ): Lijst {
        return $this->container->make(
            Lijst::class,
            [
                'id' => $id,
                'data' => $data,
            ]
        );
    }

    /**
     * Maakt een lijst uit een id aangeleverd door HTTP GET of POST.
     *
     * @param object $request HTTP-request.
     *
     * @throws GeenLijstException
     */
    public function create_lijst_uit_request(object $request): Lijst
    {
        try {
            $id = filter_var($request->lijst, \FILTER_VALIDATE_INT);
        } catch (UndefinedPropertyException) {
            throw new GeenLijstException('Geen lijst in invoer');
        }
        if ($id === false) {
            throw new GeenLijstException(sprintf(
                'Ongeldige muzieklijst id: %s',
                filter_var($request->lijst)
            ));
        }
        return $this->create_lijst($id);
    }

    /**
     * @param $id ID van het object.
     * @param ?NummerDBData $data Metadata uit de databasevelden (optioneel).
     */
    public function create_nummer(
        int $id,
        ?array $data = null,
    ): Nummer {
        return $this->container->make(
            Nummer::class,
            [
                'id' => $id,
                'data' => $data,
            ]
        );
    }

    /**
     * Maakt een nummer uit een id aangeleverd door HTTP POST.
     *
     * @param object $request HTTP-request.
     *
     * @throws GLDException
     */
    public function create_nummer_uit_request(object $request): Nummer
    {
        try {
            $id = filter_var($request->nummer, \FILTER_VALIDATE_INT);
        } catch (UndefinedPropertyException) {
            throw new GLDException('Geen nummer in invoer');
        }
        if ($id === false) {
            throw new GLDException(sprintf(
                'Ongeldige nummer id: %s',
                filter_var($request->nummer)
            ));
        }
        return $this->create_nummer($id);
    }

    /**
     * @param $id ID van het object.
     * @param ?StemmerDBData $data Metadata uit de databasevelden (optioneel).
     */
    public function create_stemmer(
        int $id,
        ?array $data = null,
    ): Stemmer {
        return $this->container->make(
            Stemmer::class,
            [
                'id' => $id,
                'data' => $data,
            ]
        );
    }

    /**
     * Maakt een stemmer uit een id aangeleverd door HTTP POST.
     *
     * @param object $request HTTP-request.
     *
     * @throws GLDException
     */
    public function create_stemmer_uit_request(object $request): Stemmer
    {
        try {
            $id = filter_var($request->stemmer, \FILTER_VALIDATE_INT);
        } catch (UndefinedPropertyException) {
            throw new GLDException('Geen stemmer in invoer');
        }
        if ($id === false) {
            throw new GLDException(sprintf(
                'Ongeldig stemmer id: %s',
                filter_var($request->stemmer)
            ));
        }
        return $this->create_stemmer($id);
    }

    public function insert_stemmer(
        Lijst $lijst,
        string $ip,
    ): Stemmer {
        try {
            $id = $this->db->insertMulti('stemmers', [
                'lijst_id' => $lijst->get_id(),
                'ip' => $ip,
            ]);
            return $this->create_stemmer($id);
        } catch (SQLDataTooLongException) {
            throw new GebruikersException('De invoer van een van de tekstvelden is te lang.');
        }
    }

    /**
     * @param $nummer
     * @param $stemmer
     * @param ?StemmerNummerDBData $data Metadata uit de databasevelden (optioneel).
     */
    public function create_stemmer_nummer(
        Nummer $nummer,
        Stemmer $stemmer,
        ?array $data = null,
    ): StemmerNummer {
        return $this->container->make(
            StemmerNummer::class,
            [
                'nummer' => $nummer,
                'stemmer' => $stemmer,
                'data' => $data,
            ]
        );
    }

    /**
     * Maakt een object uit een id aangeleverd door HTTP POST.
     *
     * @param object $request HTTP-request.
     *
     * @throws GeenLijstException
     */
    public function create_stemmer_nummer_uit_request(object $request): StemmerNummer
    {
        return $this->create_stemmer_nummer(
            $this->create_nummer_uit_request($request),
            $this->create_stemmer_uit_request($request),
        );
    }

    /**
     * @param $id Database-id van het veld.
     * @param ?VeldDBData $data Metadata uit de databasevelden (optioneel).
     * @param $verplicht Of het veld verplicht is (optioneel)
     */
    public function create_veld(
        int $id,
        ?array $data = null,
        ?bool $verplicht = null,
    ): Veld {
        return $this->container->make(
            Veld::class,
            [
                'id' => $id,
                'data' => $data,
                'verplicht' => $verplicht,
            ]
        );
    }

    /**
     * Maakt een nieuw nummer aan als vrije keuze van een stemmer.
     * Als er al een nummer bestaat met deze artiest en titel dan wordt het
     * bestaan de nummer teruggegeven.
     *
     * @return Nummer Het bestaande of nieuw toegevoegde nummer.
     *
     * @throws LegeVrijeKeuze Als de artiest of titel leeg is.
     */
    public function vrijekeuze_nummer_toevoegen(string $artiest, string $titel): Nummer
    {
        $artiest = trim($artiest);
        $titel = trim($titel);
        if ($artiest === '' || $titel === '') {
            throw new LegeVrijeKeuze();
        }
        $q_artiest = $this->db->escape_string($artiest);
        $q_titel = $this->db->escape_string($titel);
        $query = <<<EOT
        SELECT id
        FROM nummers
        WHERE
            artiest LIKE "{$q_artiest}"
            AND titel LIKE "{$q_titel}"
        EOT;
        $nummers = $this->select_objecten(Nummer::class, $query);
        if (count($nummers) > 0) {
            return $nummers[0];
        }

        $id = $this->db->insertMulti('nummers', [
            'artiest' => $artiest,
            'titel' => $titel,
            'is_vrijekeuze' => true,
        ]);
        return $this->create_nummer($id);
    }

    /**
     * @param array<mixed> $kolommen
     */
    public function create_ssp(
        object $request,
        array $kolommen
    ): SSP {
        return $this->container->make(
            SSP::class,
            [
                'request' => $request,
                'kolommen' => $kolommen,
            ]
        );
    }

    /**
     * Geeft alle muzieklijsten.
     *
     * @return list<Lijst>
     */
    public function get_muzieklijsten(): array
    {
        return $this->select_objecten(
            Lijst::class,
            'SELECT id FROM lijsten ORDER BY naam'
        );
    }

    /**
     * Geeft alle nummers.
     *
     * @return list<Nummer>
     */
    public function get_nummers(): array
    {
        return $this->select_objecten(
            Nummer::class,
            'SELECT id FROM nummers'
        );
    }

    /**
     * @return list<Veld>
     */
    public function get_velden(): array
    {
        $velden = [];
        $query = 'SELECT * FROM velden ORDER BY id';
        foreach ($this->db->query($query) as $entry) {
            $velden[] = $this->create_veld((int)$entry['id'], $entry);
        }
        return $velden;
    }

    public function create_powergold_importer(string $filename): PowergoldImporter
    {
        return $this->container->make(
            PowergoldImporter::class,
            ['filename' => $filename]
        );
    }
}
