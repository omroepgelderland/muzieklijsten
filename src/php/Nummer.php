<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

/**
 * @phpstan-type DBData array{
 *     id: positive-int,
 *     muziek_id: ?string,
 *     titel: string,
 *     artiest: string,
 *     jaar: ?int,
 *     categorie: ?string,
 *     map: ?string,
 *     opener: positive-int,
 *     duur: ?positive-int,
 *     is_vrijekeuze: positive-int
 * }
 */
class Nummer
{
    private int $id;
    private ?string $muziek_id;
    private string $titel;
    private string $artiest;
    private ?int $jaar;
    private ?string $categorie;
    private ?string $map;
    private bool $is_opener;
    private ?int $duur;
    private bool $is_vrijekeuze;
    /** @var list<Lijst> */
    private array $lijsten;
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
     * Geeft aan of twee nummers dezelfde zijn. Wanneer $obj geen Nummer is wordt false gegeven.
     *
     * @param $obj Object om deze instantie mee te vergelijken
     *
     * @return bool Of $obj hetzelfde nummer is als deze instantie
     */
    public function equals(mixed $obj): bool
    {
        return ( $obj instanceof Nummer && $obj->get_id() == $this->id );
    }

    /**
     * Geeft het Powergold ID
     */
    public function get_muziek_id(): ?string
    {
        $this->set_db_properties();
        return $this->muziek_id;
    }

    public function get_titel(): string
    {
        $this->set_db_properties();
        return $this->titel;
    }

    public function get_artiest(): string
    {
        $this->set_db_properties();
        return $this->artiest;
    }

    public function get_jaar(): ?int
    {
        $this->set_db_properties();
        return $this->jaar;
    }

    public function get_categorie(): ?string
    {
        $this->set_db_properties();
        return $this->categorie;
    }

    public function get_map(): ?string
    {
        $this->set_db_properties();
        return $this->map;
    }

    public function is_opener(): bool
    {
        $this->set_db_properties();
        return $this->is_opener;
    }

    public function get_duur(): ?int
    {
        $this->set_db_properties();
        return $this->duur;
    }

    public function is_vrijekeuze(): bool
    {
        $this->set_db_properties();
        return $this->is_vrijekeuze;
    }

    /**
     * Geeft alle lijsten waar dit nummer op staat.
     *
     * @return list<Lijst>
     */
    public function get_lijsten(): array
    {
        if (!isset($this->lijsten)) {
            $query = <<<EOT
            SELECT lijst_id AS id
            FROM lijsten_nummers
            WHERE nummer_id = {$this->get_id()}
            EOT;
            $this->lijsten = $this->factory->select_objecten(Lijst::class, $query);
        }
        return $this->lijsten;
    }

    /**
     * Vul het object met velden uit de database.
     */
    private function set_db_properties(): void
    {
        if (!$this->db_props_set) {
            $this->set_data($this->db->selectSingleRow(sprintf(
                'SELECT * FROM nummers WHERE id = %d',
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
        $this->muziek_id = $data['muziek_id'];
        $this->titel = $data['titel'];
        $this->artiest = $data['artiest'];
        $this->jaar = isset($data['jaar'])
            ? (int)$data['jaar']
            : null;
        $this->categorie = $data['categorie'];
        $this->map = $data['map'];
        $this->is_opener = (bool)$data['opener'];
        $this->duur = $data['duur'];
        $this->is_vrijekeuze = (bool)$data['is_vrijekeuze'];
        $this->db_props_set = true;
    }
}
