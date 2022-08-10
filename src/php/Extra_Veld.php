<?php

namespace muzieklijsten;

class Extra_Veld {
	
	private int $id;
	private string $label;
	private string $placeholder;
	private string $type;
	private string $leeg_feedback;
	private bool $verplicht;
	private Lijst $lijsten;
	private bool $db_props_set;
	
	/**
	 * 
	 * @param int $id Database-id van het veld.
	 * @param ?array $data Metadata uit de databasevelden (optioneel).
	 * @param boolean $verplicht Of het veld verplicht is (optioneel)
	 */
	public function __construct( int $id, ?array $data = null, ?bool $verplicht=null ) {
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
	 * Geeft aan of twee extra velden dezelfde zijn. Wanneer $obj geen Extra_Veld is wordt false gegeven.
	 * @param mixed $obj Object om deze instantie mee te vergelijken
	 * @return bool Of $obj hetzelfde extra veld is als deze instantie
	 */
	public function equals( $obj ): bool {
		return ( $obj instanceof Extra_Veld && $obj->get_id() == $this->id );
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
	 */
	public function get_placeholder(): string {
		$this->set_db_properties();
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
	 * Geeft alle lijsten waar dit een extra veld is.
	 * return Muzieklijst[]
	 */
	public function get_lijsten(): array {
		if ( !isset($this->lijsten) ) {
			$this->lijsten = [];
			$sql = sprintf(
				'SELECT lijst_id FROM lijsten_extra_velden WHERE extra_veld_id = %d',
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
		return sprintf('extra-veld-%d', $this->get_id());
	}
	
	/**
	 * Geeft een stukje HTML voor het invoerveld binnen een formulier
	 * @return string
	 */
	public function get_formulier_html(): string {
		$id = $this->get_html_id();
		$label = htmlspecialchars($this->get_label());
		$type = htmlspecialchars($this->get_type());
		$placeholder = htmlspecialchars($this->get_placeholder());
		
		if ( $this->is_verplicht() ) {
			$required_str = ' required';
		} else {
			$required_str = '';
		}
		
		return <<<EOT
			<div class="form-group">
				<label class="control-label col-sm-2" for="{$id}">{$label}</label>
				<div class="col-sm-10">
					<input type="{$type}" class="form-control" id="{$id}" name="{$id}" placeholder="{$placeholder}" data-leeg-feedback="{$this->get_leeg_feedback()}"{$required_str}>
				</div>
			</div>
		EOT;
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
				'SELECT waarde FROM stemmers_extra_velden WHERE stemmer_id = %d AND extra_veld_id = %d',
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
				'SELECT * FROM extra_velden WHERE id = %d',
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
		$this->placeholder = $data['placeholder'];
		$this->type = $data['type'];
		$this->leeg_feedback = $data['leeg_feedback'];
		$this->db_props_set = true;
	}

	public function add_waarde( Stemmer $stemmer, string $waarde ): void {
		$id = DB::insertMulti('stemmers_extra_velden', [
			'stemmer_id' => $stemmer->get_id(),
			'extra_veld_id' => $this->get_id(),
			'waarde' => $waarde
		]);
	}
}
