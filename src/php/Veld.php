<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

class Veld {
	
	private int $id;
	private string $label;
	private string $leeg_feedback;
	private ?int $max;
	private ?int $maxlength;
	private ?int $min;
	private ?int $minlength;
	private ?string $placeholder;
	private string $type;
	private ?bool $verplicht;
	private Lijst $lijsten;
	private bool $db_props_set;
	
	/**
	 * 
	 * @param int $id Database-id van het veld.
	 * @param ?array $data Metadata uit de databasevelden (optioneel).
	 * @param boolean $verplicht Of het veld verplicht is (optioneel)
	 */
	public function __construct( int $id, ?array $data = null, ?bool $verplicht = null ) {
		$this->id = $id;
		$this->db_props_set = false;
		$this->verplicht = $verplicht;
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
	 * Geeft aan of twee velden dezelfde zijn. Wanneer $obj geen Veld is wordt false gegeven.
	 * @param mixed $obj Object om deze instantie mee te vergelijken
	 * @return bool Of $obj hetzelfde veld is als deze instantie
	 */
	public function equals( $obj ): bool {
		return ( $obj instanceof Veld && $obj->get_id() == $this->id );
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_label(): string {
		$this->set_db_properties();
		return $this->label;
	}
	
	/**
	 * 
	 * @return string
	 * @throws ObjectEigenschapOntbreekt
	 */
	public function get_placeholder(): string {
		$this->set_db_properties();
		if ( !isset($this->placeholder) ) {
			throw new ObjectEigenschapOntbreekt();
		}
		return $this->placeholder;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_type(): string {
		$this->set_db_properties();
		return $this->type;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_leeg_feedback(): string {
		$this->set_db_properties();
		return $this->leeg_feedback;
	}
	
	/**
	 * Geeft aan of het invullen van dit veld verplicht is.
	 * Dat is geen vaste eigenschap van het veld, maar van de combinatie veld en lijst. Of het verplicht is moet bij de constructor worden meegegeven.
	 * @return bool
	 * @throws Muzieklijsten_Exception Als er bij de constructor niet is aangegeven of het veld verplicht is.
	 */
	public function is_verplicht(): bool {
		if ( !isset($this->verplicht) ) {
			throw new Muzieklijsten_Exception('Niet bekend of dit veld verplicht is.');
		}
		return $this->verplicht;
	}
	
	/**
	 * Geeft alle lijsten waar dit een veld is.
	 * return Muzieklijst[]
	 */
	public function get_lijsten(): array {
		if ( !isset($this->lijsten) ) {
			$this->lijsten = [];
			$sql = sprintf(
				'SELECT lijst_id FROM lijsten_velden WHERE veld_id = %d',
				$this->get_id()
			);
			foreach ( DB::selectSingleColumn($sql) as $lijst_id ) {
				$this->lijsten[] = new Lijst($lijst_id);
			}
		}
		return $this->lijsten;
	}
	
	/**
	 * Geeft het id van het invoerveld in het HTML-formulier
	 * @return string
	 */
	public function get_html_id(): string {
		return sprintf('veld-%d', $this->get_id());
	}
	
	/**
	 * Geeft het antwoord dat een stemmer hier heeft ingevuld.
	 * @throws Muzieklijsten_Exception als er geen antwoord is.
	 * @param Stemmer $stemmer
	 * @return mixed Waarde
	 */
	public function get_stemmer_waarde( Stemmer $stemmer ) {
		try {
			$waarde = DB::selectSingle(sprintf(
				'SELECT waarde FROM stemmers_velden WHERE stemmer_id = %d AND veld_id = %d',
				$stemmer->get_id(),
				$this->get_id()
			));
			if ( $waarde === null ) {
				throw new Muzieklijsten_Exception();
			}
		} catch ( Muzieklijsten_Exception $e ) {
			throw new Muzieklijsten_Exception(sprintf(
				'Geen waarde ingevuld door stemmer %d in veld %d',
				$stemmer->get_id(),
				$this->get_id()
			), $e->getCode(), $e);
		}
		return $waarde;
	}
	
	/**
	 * Vul het object met velden uit de database.
	 */
	private function set_db_properties(): void {
		if ( !$this->db_props_set ) {
			$this->set_data(DB::selectSingleRow(sprintf(
				'SELECT * FROM velden WHERE id = %d',
				$this->get_id()
			)));
		}
	}

	/**
	 * Plaatst metadata in het object
	 * @param array $data Data.
	 */
	private function set_data( array $data ): void {
		$this->label = $data['label'];
		$this->leeg_feedback = $data['leeg_feedback'];
		$this->max = $data['max'];
		$this->maxlength = $data['maxlength'];
		$this->min = $data['min'];
		$this->minlength = $data['minlength'];
		$this->placeholder = $data['placeholder'];
		$this->type = $data['type'];
		$this->db_props_set = true;
	}

	public function add_waarde( Stemmer $stemmer, string $waarde ): void {
		DB::insertMulti('stemmers_velden', [
			'stemmer_id' => $stemmer->get_id(),
			'veld_id' => $this->get_id(),
			'waarde' => $waarde
		]);
	}

	/**
	 * @return int
	 * @throws ObjectEigenschapOntbreekt
	 */
	public function get_max(): int {
		$this->set_db_properties();
		if ( !isset($this->max) ) {
			throw new ObjectEigenschapOntbreekt();
		}
		return $this->max;
	}

	/**
	 * @return int
	 * @throws ObjectEigenschapOntbreekt
	 */
	public function get_maxlength(): int {
		$this->set_db_properties();
		if ( !isset($this->maxlength) ) {
			throw new ObjectEigenschapOntbreekt();
		}
		return $this->maxlength;
	}

	/**
	 * @return int
	 * @throws ObjectEigenschapOntbreekt
	 */
	public function get_min(): int {
		$this->set_db_properties();
		if ( !isset($this->min) ) {
			throw new ObjectEigenschapOntbreekt();
		}
		return $this->min;
	}

	/**
	 * @return int
	 * @throws ObjectEigenschapOntbreekt
	 */
	public function get_minlength(): int {
		$this->set_db_properties();
		if ( !isset($this->minlength) ) {
			throw new ObjectEigenschapOntbreekt();
		}
		return $this->minlength;
	}
}
