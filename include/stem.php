<?php

class Stem {

	/** @var int */
	private $id;
	/** @var Nummer */
	private $nummer;
	/** @var Muzieklijst */
	private $lijst;
	/** @var Stemmer */
	private $stemmer;
	/** @var string */
	private $toelichting;
	/** @var string */
	private $eigenkeuze;
	/** @var boolean */
	private $behandeld;
	/** @var boolean */
	private $db_props_set = false;
	
	public function __construct( $id ) {
		$this->id = $id;
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
	 * @return Nummer
	 */
	public function get_nummer() {
		$this->set_db_properties();
		return $this->nummer;
	}
	
	/**
	 * 
	 * @return Muzieklijst
	 */
	public function get_lijst() {
		$this->set_db_properties();
		return $this->lijst;
	}
	
	/**
	 * 
	 * @return Stemmer
	 */
	public function get_stemmer() {
		$this->set_db_properties();
		return $this->stemmer;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_toelichting() {
		$this->set_db_properties();
		return $this->toelichting;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_eigenkeuze() {
		$this->set_db_properties();
		return $this->eigenkeuze;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function is_behandeld() {
		$this->set_db_properties();
		return $this->behandeld;
	}
	
	/**
	 * Vul het object met velden uit de database.
	 */
	private function set_db_properties() {
		if ( !$this->db_props_set ) {
			$entry = Muzieklijsten_Database::selectSingleRow(sprintf(
				'SELECT * FROM muzieklijst_stemmen WHERE id = %d',
				$this->get_id()
			));
			$this->nummer = new Nummer($entry['nummer_id']);
			$this->lijst = new Muzieklijst($entry['lijst_id']);
			$this->stemmer = new Stemmer($entry['stemmer_id']);
			$this->toelichting = $entry['toelichting'];
			$this->eigenkeuze = $entry['eigenkeuze'];
			$this->behandeld = $entry['behandeld'] == 1;
			$this->db_props_set = true;
		}
	}
}
