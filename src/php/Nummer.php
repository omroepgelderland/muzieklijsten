<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
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
 *     is_vrijekeuze: positive-int
 * }
 */
class Nummer {

    private int $id;
    private ?string $muziek_id;
    private string $titel;
    private string $artiest;
    private ?int $jaar;
    private ?string $categorie;
    private ?string $map;
    private bool $is_opener;
    private bool $is_vrijekeuze;
    /** @var list<Lijst> */
    private array $lijsten;
    private bool $db_props_set;
    
    /**
     * @param $id ID van het object.
     * @param ?DBData $data Metadata uit de databasevelden (optioneel).
     */
    public function __construct( int $id, ?array $data = null ) {
        $this->id = $id;
        $this->db_props_set = false;
        if ( isset($data) ) {
            $this->set_data($data);
        }
    }
    
    public function get_id(): int {
        return $this->id;
    }
    
    /**
     * Geeft aan of twee nummers dezelfde zijn. Wanneer $obj geen Nummer is wordt false gegeven.
     * @param $obj Object om deze instantie mee te vergelijken
     * @return bool Of $obj hetzelfde nummer is als deze instantie
     */
    public function equals( mixed $obj ): bool {
        return ( $obj instanceof Nummer && $obj->get_id() == $this->id );
    }
    
    /**
     * Geeft het Powergold ID
     */
    public function get_muziek_id(): ?string {
        $this->set_db_properties();
        return $this->muziek_id;
    }
    
    public function get_titel(): string {
        $this->set_db_properties();
        return $this->titel;
    }
    
    public function get_artiest(): string {
        $this->set_db_properties();
        return $this->artiest;
    }
    
    public function get_jaar(): ?int {
        $this->set_db_properties();
        return $this->jaar;
    }
    
    public function get_categorie(): ?string {
        $this->set_db_properties();
        return $this->categorie;
    }
    
    public function get_map(): ?string {
        $this->set_db_properties();
        return $this->map;
    }
    
    public function is_opener(): bool {
        $this->set_db_properties();
        return $this->is_opener;
    }
    
    public function is_vrijekeuze(): bool {
        $this->set_db_properties();
        return $this->is_vrijekeuze;
    }
    
    /**
     * Geeft alle lijsten waar dit nummer op staat.
     * @return list<Lijst>
     */
    public function get_lijsten(): array {
        if ( !isset($this->lijsten) ) {
            $query = <<<EOT
            SELECT lijst_id
            FROM lijsten_nummers
            WHERE nummer_id = {$this->get_id()}
            EOT;
            $this->lijsten = DB::selectObjectLijst($query, Lijst::class);
        }
        return $this->lijsten;
    }
    
    /**
     * Vul het object met velden uit de database.
     */
    private function set_db_properties(): void {
        if ( !$this->db_props_set ) {
            $this->set_data(DB::selectSingleRow(sprintf(
                'SELECT * FROM nummers WHERE id = %d',
                $this->get_id()
            )));
        }
    }

    /**
     * Plaatst metadata in het object
     * @param DBData $data Data.
     */
    private function set_data( array $data ): void {
        $this->muziek_id = $data['muziek_id'];
        $this->titel = $data['titel'];
        $this->artiest = $data['artiest'];
        $this->jaar = $data['jaar'];
        $this->categorie = $data['categorie'];
        $this->map = $data['map'];
        $this->is_opener = $data['opener'] == 1;
        $this->is_vrijekeuze = $data['is_vrijekeuze'] == 1;
        $this->db_props_set = true;
    }

    /**
     * Maakt een object uit een id aangeleverd door HTTP POST.
     * @param object $request HTTP-request.
     * @throws Muzieklijsten_Exception
     */
    public static function maak_uit_request( object $request ): self {
        try {
            $id = filter_var($request->nummer, FILTER_VALIDATE_INT);
        } catch ( UndefinedPropertyException ) {
            throw new Muzieklijsten_Exception('Geen nummer in invoer');
        }
        if ( $id === false ) {
            throw new Muzieklijsten_Exception(sprintf(
                'Ongeldige nummer id: %s',
                filter_var($request->nummer)
            ));
        }
        return new self($id);
    }

    /**
     * Maakt een nieuw nummer aan als vrije keuze van een stemmer.
     * Als er al een nummer bestaat met deze artiest en titel dan wordt het
     * bestaan de nummer teruggegeven.
     * @return self Het bestaande of nieuw toegevoegde nummer.
     * @throws LegeVrijeKeuze Als de artiest of titel leeg is.
     */
    public static function vrijekeuze_toevoegen( string $artiest, string $titel ): self {
        $artiest = trim($artiest);
        $titel = trim($titel);
        if ( $artiest === '' || $titel === '' ) {
            throw new LegeVrijeKeuze();
        }
        $q_artiest = DB::escape_string($artiest);
        $q_titel = DB::escape_string($titel);
        $query = <<<EOT
        SELECT id
        FROM nummers
        WHERE
            artiest LIKE "{$q_artiest}"
            AND titel LIKE "{$q_titel}"
        EOT;
        $nummers = DB::selectObjectLijst($query, self::class);
        if ( count($nummers) > 0 ) {
            return $nummers[0];
        }

        $id = DB::insertMulti('nummers', [
            'artiest' => $artiest,
            'titel' => $titel,
            'is_vrijekeuze' => true
        ]);
        return new self($id);
    }


}
