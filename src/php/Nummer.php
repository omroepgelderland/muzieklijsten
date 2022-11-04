<?php

namespace muzieklijsten;

class Nummer {

	private int $id;
	private ?string $muziek_id;
	private string $titel;
	private string $artiest;
	private ?int $jaar;
	private ?string $categorie;
	private ?string $map;
	private bool $is_opener;
	/** @var Muzieklijst[] */
	private array $lijsten;
	private bool $db_props_set;
	
	/**
	 * @param int $id ID van het object.
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
	 * 
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}
	
	/**
	 * Geeft aan of twee nummers dezelfde zijn. Wanneer $obj geen Nummer is wordt false gegeven.
	 * @param mixed $obj Object om deze instantie mee te vergelijken
	 * @return boolean Of $obj hetzelfde nummer is als deze instantie
	 */
	public function equals( $obj ): bool {
		return ( $obj instanceof Nummer && $obj->get_id() == $this->id );
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_muziek_id(): ?string {
		$this->set_db_properties();
		return $this->muziek_id;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_titel(): string {
		$this->set_db_properties();
		return $this->titel;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_artiest(): string {
		$this->set_db_properties();
		return $this->artiest;
	}
	
	/**
	 * 
	 * @return int|null
	 */
	public function get_jaar(): ?int {
		$this->set_db_properties();
		return $this->jaar;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_categorie(): ?string {
		$this->set_db_properties();
		return $this->categorie;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_map(): ?string {
		$this->set_db_properties();
		return $this->map;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function is_opener(): bool {
		$this->set_db_properties();
		return $this->is_opener;
	}
	
	/**
	 * Geeft alle lijsten waar dit nummer op staat.
	 * @return Muzieklijst[]
	 */
	public function get_lijsten(): array {
		if ( !isset($this->lijsten) ) {
			$this->lijsten = [];
			$sql = sprintf(
				'SELECT lijst_id FROM lijsten_nummers WHERE nummer_id = %d',
				$this->get_id()
			);
			foreach ( DB::selectSingleColumn($sql) as $lijst_id ) {
				$this->lijsten[] = new Lijst($lijst_id);
			}
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
	 * @param array $data Data.
	 */
	private function set_data( array $data ): void {
		$this->muziek_id = $data['muziek_id'];
		$this->titel = $data['titel'];
		$this->artiest = $data['artiest'];
		$this->jaar = $data['jaar'];
		$this->categorie = $data['categorie'];
		$this->map = $data['map'];
		$this->is_opener = $data['opener'] == 1;
		$this->db_props_set = true;
	}

	/**
	 * Maakt een object uit een id aangeleverd door HTTP GET of POST.
	 * @param int $type INPUT_GET of INPUT_POST. Standaard is INPUT_POST.
	 * @return Nummer
	 * @throws Muzieklijsten_Exception
	 */
	public static function maak_uit_request( int $type = INPUT_POST ): Nummer {
		$id = filter_input($type, 'nummer', FILTER_VALIDATE_INT);
		if ( $id === null ) {
			throw new Muzieklijsten_Exception('Geen nummer in invoer');
		}
		if ( $id === false ) {
			throw new Muzieklijsten_Exception(sprintf(
				'Ongeldige nummer id: %s',
				filter_input($type, 'nummer')
			));
		}
		return new static($id);
	}

	/**
	 * Haalt de toelichting op een nummer uit het POST-request bij een stemactie.
	 * Superomslachtige hack; komt door overname legacy code. Kan een keer
	 * verbeterd worden
	 * @return string De toelichting.
	 */
	public function get_post_toelichting(): string {
		return filter_input(INPUT_POST, "id_{$this->get_id()}");
	}

}
