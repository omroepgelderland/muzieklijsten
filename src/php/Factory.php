<?php

declare(strict_types=1);

namespace muzieklijsten;

use DI\FactoryInterface;
use gldstdlib\exception\GLDException;
use gldstdlib\exception\SQLDataTooLongException;
use gldstdlib\exception\SQLNoResultException;
use Psr\Container\ContainerInterface;

/**
 * @phpstan-import-type LijstData from \muzieklijsten\Lijst
 * @phpstan-import-type NummerData from \muzieklijsten\Nummer
 * @phpstan-import-type StemmerData from \muzieklijsten\Stemmer
 * @phpstan-import-type StemmerNummerData from \muzieklijsten\StemmerNummer
 * @phpstan-import-type VeldData from \muzieklijsten\Veld
 * @phpstan-import-type SearchType from \muzieklijsten\SSP
 */
class Factory
{
    public function __construct(
        private ContainerInterface $container,
        private FactoryInterface $factory,
        private DB $db,
    ) {
    }

    /**
     * @template T of object
     *
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param class-string<T> $id Identifier of the entry to look for.
     *
     * @return T Entry.
     */
    public function get_object(string $id): object
    {
        return $this->container->get($id);
    }

    /**
     * Maakt een instantie van een object aan de hand van een databasequery.
     *
     * @template T of object
     *
     * @param class-string<T> $class Type object.
     * @param $query Databasequery. Verwacht één kolom in het SELECT-statement.
     * De waarde daarvan moet een integer zijn.
     *
     * @return T
     *
     * @throws \gldstdlib\exception\SQLException Als de query mislukt.
     * @throws \gldstdlib\exception\SQLException Als er geen verbinding kan
     * worden gemaakt met de database.
     * @throws \gldstdlib\exception\SQLNoResultException Als er geen resultaat is.
     */
    public function select_object(string $class, string $query): object
    {
        return $this->factory->make(
            $class,
            [
                'id' => $this->db->selectSingle($query),
            ],
        );
    }

    /**
     * Maakt een lijst met instanties van objecten aan de hand van een
     * databasequery.
     *
     * @template T of object
     *
     * @param class-string<T> $class Type object.
     * @param $query Databasequery. Verwacht één kolom in het SELECT-statement.
     * De waardes daarvan moeten integers zijn.
     *
     * @return \Iterator<int, T>
     */
    public function select_objecten(string $class, string $query): \Iterator
    {
        foreach ($this->db->selectSingleColumnInt($query) as $id) {
            yield $this->factory->make(
                $class,
                ['id' => $id],
            );
        }
    }

    /**
     * @param array<string, mixed> $request Requestdata van de frontend.
     */
    public function create_ajax(
        array $request,
    ): Ajax {
        return $this->factory->make(
            Ajax::class,
            ['request' => $request],
        );
    }

    /**
     * @param $id ID van de lijst.
     */
    public function create_lijst(
        int $id,
    ): Lijst {
        return $this->factory->make(
            Lijst::class,
            [
                'id' => $id,
            ],
        );
    }

    /**
     * @param array<string, string|null> $entry
     */
    public function create_lijst_uit_db_entry(
        array $entry,
    ): Lijst {
        $lijst = $this->create_lijst((int)$entry['id']);
        $lijst->set_db_data($entry);
        return $lijst;
    }

    /**
     * Maakt een lijst uit een id aangeleverd door HTTP GET of POST.
     *
     * @param array<string, mixed> $request HTTP-request.
     *
     * @throws GeenLijstException
     */
    public function create_lijst_uit_request(array $request): Lijst
    {
        $id = filter_var($request['lijst'] ?? null, \FILTER_VALIDATE_INT);
        if ($id === false) {
            throw new GeenLijstException('Geen lijst in invoer');
        }
        $id = (int)$id;
        if ($id < 1) {
            throw new GeenLijstException(\sprintf(
                'Ongeldige muzieklijst id: %s',
                filter_var($request['lijst']),
            ));
        }
        return $this->create_lijst($id);
    }

    /**
     * @param $id ID van het nummer.
     */
    public function create_nummer(
        int $id,
    ): Nummer {
        return $this->factory->make(
            Nummer::class,
            [
                'id' => $id,
            ],
        );
    }

    /**
     * @param array<string, string|null> $entry
     */
    public function create_nummer_uit_db_entry(
        array $entry,
    ): Nummer {
        $nummer = $this->create_nummer((int)$entry['id']);
        $nummer->set_db_data($entry);
        return $nummer;
    }

    /**
     * Maakt een nummer uit een id aangeleverd door HTTP POST.
     *
     * @param array<string, mixed> $request HTTP-request.
     *
     * @throws GLDException
     */
    public function create_nummer_uit_request(array $request): Nummer
    {
        $id = filter_var($request['nummer'] ?? null, \FILTER_VALIDATE_INT);
        if ($id === false) {
            throw new GLDException('Geen nummer in invoer');
        }
        $id = (int)$id;
        if ($id < 1) {
            throw new GLDException(\sprintf(
                'Ongeldige nummer id: %s',
                filter_var($request['nummer']),
            ));
        }
        return $this->create_nummer($id);
    }

    /**
     * @param $id ID van de stemmer.
     */
    public function create_stemmer(
        int $id,
    ): Stemmer {
        return $this->factory->make(
            Stemmer::class,
            [
                'id' => $id,
            ],
        );
    }

    /**
     * @param array<string, string|null> $entry
     */
    public function create_stemmer_uit_db_entry(
        array $entry,
    ): Stemmer {
        $stemmer = $this->create_stemmer((int)$entry['id']);
        $stemmer->set_db_data($entry);
        return $stemmer;
    }

    /**
     * Maakt een stemmer uit een id aangeleverd door HTTP POST.
     *
     * @param array<string, mixed> $request HTTP-request.
     *
     * @throws GLDException
     */
    public function create_stemmer_uit_request(array $request): Stemmer
    {
        $id = filter_var($request['stemmer'] ?? null, \FILTER_VALIDATE_INT);
        if ($id === false) {
            throw new GLDException('Geen stemmer in invoer');
        }
        $id = (int)$id;
        if ($id < 1) {
            throw new GLDException(\sprintf(
                'Ongeldig stemmer id: %s',
                filter_var($request['stemmer']),
            ));
        }
        return $this->create_stemmer($id);
    }

    public function insert_stemmer(
        Lijst $lijst,
        string $ip,
    ): Stemmer {
        try {
            $id = (int)$this->db->insertMulti('stemmers', [
                'lijst_id' => $lijst->id,
                'ip' => $ip,
            ]);
            return $this->create_stemmer($id);
        } catch (SQLDataTooLongException) {
            throw new GebruikersException('De invoer van een van de tekstvelden is te lang.');
        }
    }

    public function create_stemmer_nummer(
        Nummer $nummer,
        Stemmer $stemmer,
    ): StemmerNummer {
        return $this->factory->make(
            StemmerNummer::class,
            [
                'nummer' => $nummer,
                'stemmer' => $stemmer,
            ],
        );
    }

    /**
     * Maakt een object uit een id aangeleverd door HTTP POST.
     *
     * @param array<string, mixed> $request HTTP-request.
     *
     * @throws GeenLijstException
     */
    public function create_stemmer_nummer_uit_request(array $request): StemmerNummer
    {
        return $this->create_stemmer_nummer(
            $this->create_nummer_uit_request($request),
            $this->create_stemmer_uit_request($request),
        );
    }

    /**
     * @param $id Database-id van het veld.
     * @param $verplicht Of het veld verplicht is (optioneel)
     */
    public function create_veld(
        int $id,
        ?bool $verplicht = null,
    ): Veld {
        return $this->factory->make(
            Veld::class,
            [
                'id' => $id,
                'verplicht' => $verplicht,
            ],
        );
    }

    /**
     * @param array<string, string|null> $entry
     */
    public function create_veld_uit_db_entry(
        array $entry,
    ): Veld {
        $veld = $this->create_veld((int)$entry['id']);
        $veld->set_db_data($entry);
        return $veld;
    }

    /**
     * Maakt een nieuw nummer aan als vrije keuze van een stemmer.
     * Als er al een nummer bestaat met deze artiest en titel dan wordt het
     * bestaande nummer teruggegeven.
     *
     * @return Nummer Het bestaande of nieuw toegevoegde nummer.
     *
     * @throws LegeVrijeKeuze Als de artiest of titel leeg is.
     */
    public function vrijekeuze_nummer_toevoegen(string $artiest, string $titel): Nummer
    {
        $artiest = \trim($artiest);
        $titel = \trim($titel);
        $vgl_artiest = get_vgl_string($artiest, true);
        $vgl_titel = get_vgl_string($titel, false);
        if ($vgl_artiest === '' || $vgl_titel === '') {
            throw new LegeVrijeKeuze();
        }
        $q_artiest = $this->db->escape_string($vgl_artiest);
        $q_titel = $this->db->escape_string($vgl_titel);
        $query = <<<SQL
        SELECT id
        FROM nummers
        WHERE
            vgl_artiest = "{$q_artiest}"
            AND vgl_titel = "{$q_titel}"
        ORDER BY id
        LIMIT 1
        SQL;

        try {
            return $this->select_object(Nummer::class, $query);
        } catch (SQLNoResultException) {
        }

        return $this->insert_nummer(
            artiest: $artiest,
            titel: $titel,
            is_vrijekeuze: 1,
        );
    }

    /**
     * @param SearchType $search
     * @param array<int, array{
     *     column: int,
     *     dir: string,
     *     name: string,
     * }> $order
     * @param array<int, array{
     *     data: string,
     *     name: string,
     *     searchable: bool,
     *     orderable: bool,
     *     search: SearchType,
     * }> $columns
     */
    public function create_ssp(
        int $draw,
        int $start,
        int $length,
        array $search,
        array $order,
        array $columns,
        ?Lijst $lijst,
        bool $random_volgorde = false,
        ?bool $is_vrijekeuze = null,
        ?int $random_seed = null,
    ): SSP {
        return $this->factory->make(
            SSP::class,
            [
                'lijst' => $lijst,
                'draw' => $draw,
                'start' => $start,
                'length' => $length,
                'search' => $search,
                'order' => $order,
                'columns' => $columns,
                'random_volgorde' => $random_volgorde,
                'is_vrijekeuze' => $is_vrijekeuze,
                'random_seed' => $random_seed,
            ],
        );
    }

    /**
     * Geeft alle muzieklijsten.
     *
     * @return \Iterator<int, Lijst>
     */
    public function get_muzieklijsten(): \Iterator
    {
        return $this->select_objecten(
            Lijst::class,
            'SELECT id FROM lijsten ORDER BY naam',
        );
    }

    /**
     * Geeft alle nummers.
     *
     * @return \Iterator<int, Nummer>
     */
    public function get_nummers(): \Iterator
    {
        return $this->select_objecten(
            Nummer::class,
            'SELECT id FROM nummers',
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
            $veld = $this->create_veld((int)$entry['id']);
            $veld->set_db_data($entry);
            $velden[] = $veld;
        }
        return $velden;
    }

    public function create_powergold_importer(string $filename): PowergoldImporter
    {
        return $this->factory->make(
            PowergoldImporter::class,
            ['filename' => $filename],
        );
    }

    /**
     * Maakt een nieuw nummer in de database.
     *
     * @return Nummer het nieuwe nummer
     *
     * @throws \gldstdlib\exception\SQLDupEntryException Als er al een nummer
     * bestaat met dit powergold-id of artiest-titel-jaar.
     */
    public function insert_nummer(
        string $titel,
        string $artiest,
        ?string $powergold_id = null,
        ?int $jaar = null,
        ?string $categorie = null,
        ?string $map = null,
        bool $opener = false,
        ?int $duur = null,
        int $is_vrijekeuze = 0,
    ): Nummer {
        $data = [
            'muziek_id' => $powergold_id,
            'titel' => $titel,
            'artiest' => $artiest,
            'jaar' => $jaar,
            'categorie' => $categorie,
            'map' => $map,
            'is_opener' => $opener,
            'duur' => $duur,
            'is_vrijekeuze' => $is_vrijekeuze,
            'vgl_artiest' => get_vgl_string($artiest, true),
            'vgl_titel' => get_vgl_string($titel, false),
        ];
        $id = (int)$this->db->insertMulti('nummers', $data);
        $nummer = $this->create_nummer($id);
        $nummer->set_data($data);
        return $nummer;
    }
}
