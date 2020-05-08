<?php

class Extra_Veld {
	
	/** @var int */
	private $id;
	/** @var string */
	private $label;
	/** @var string */
	private $placeholder;
	/** @var string */
	private $type;
	/** @var string */
	private $leeg_feedback;
	/** @var boolean */
	private $verplicht;
	/** @var boolean */
	private $db_props_set;
	
	/**
	 * 
	 * @param int $id Database-id van het veld.
	 * @param boolean $verplicht Of het veld verplicht is (optioneel)
	 */
	public function __construct( $id, $verplicht=null ) {
		$this->id = $id;
		$this->verplicht = $verplicht;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_label() {
		$this->set_db_properties();
		return $this->label;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_placeholder() {
		$this->set_db_properties();
		return $this->placeholder;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_type() {
		$this->set_db_properties();
		return $this->type;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_leeg_feedback() {
		$this->set_db_properties();
		return $this->leeg_feedback;
	}
	
	/**
	 * Geeft aan of het invullen van dit veld verplicht is.
	 * Dat is geen vaste eigenschap van het veld, maar van de combinatie veld en lijst. Of het verplicht is moet bij de constructor worden meegegeven.
	 * @return boolean
	 * @throws Muzieklijsten_Exception Als er bij de constructor niet is aangegeven of het veld verplicht is.
	 */
	public function is_verplicht() {
		if ( $this->verplicht === null ) {
			throw new Muzieklijsten_Exception('Niet bekend of dit veld verplicht is.');
		}
		return $this->verplicht;
	}
	
	/**
	 * Geeft het id van het invoerveld in het HTML-formulier
	 * @return string
	 */
	public function get_html_id() {
		return sprintf('extra-veld-%d', $this->get_id());
	}
	
	/**
	 * Geeft een stukje HTML voor het invoerveld binnen een formulier
	 * @return string
	 */
	public function get_formulier_html() {
		$id = $this->get_html_id();
		
		if ( $this->is_verplicht() ) {
			$required_str = 'required';
		} else {
			$required_str = '';
		}
		
		return sprintf('<div class="form-group"><label class="control-label col-sm-2" for="%s">%s</label><div class="col-sm-10"><input type="%s" class="form-control" id="%s" name="%s" placeholder="%s" data-leeg-feedback="%s" %s></div></div>',
			$id,
			$this->get_label(),
			$this->get_type(),
			$id,
			$id,
			$this->get_placeholder(),
			$this->get_leeg_feedback(),
			$required_str
		);
	}
	
	/**
	 * Geeft het antwoord dat een stemmer hier heeft ingevuld.
	 * @throws Muzieklijsten_Exception als er geen antwoord is.
	 * @param Stemmer $stemmer
	 * @return mixed Waarde
	 */
	public function get_stemmer_waarde( $stemmer ) {
		try {
			$waarde = Muzieklijsten_Database::selectSingle(sprintf(
				'SELECT waarde FROM muzieklijst_stemmers_extra_velden WHERE stemmer_id = %d AND extra_veld_id = %d',
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
	}
	
	/**
	 * Vul het object met velden uit de database.
	 */
	private function set_db_properties() {
		if ( !$this->db_props_set ) {
			$entry = Muzieklijsten_Database::selectSingleRow(sprintf(
				'SELECT * FROM muzieklijst_extra_velden WHERE id = %d',
				$this->get_id()
			));
			$this->label = $entry['label'];
			$this->placeholder = $entry['placeholder'];
			$this->type = $entry['type'];
			$this->leeg_feedback = $entry['leeg_feedback'];
			$this->db_props_set = true;
		}
	}
}
