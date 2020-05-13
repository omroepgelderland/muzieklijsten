<?php

class Nummer {

	/** @var int */
	private $id;
	/** @var string|null */
	private $muziek_id;
	/** @var string */
	private $titel;
	/** @var string */
	private $artiest;
	/** @var int|null */
	private $jaar;
	/** @var string|null */
	private $categorie;
	/** @var string|null */
	private $map;
	/** @var boolean */
	private $is_opener;
	/** @var Muzieklijst[] */
	private $lijsten;
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
	 * @return string|null
	 */
	public function get_muziek_id() {
		$this->set_db_properties();
		return $this->muziek_id;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_titel() {
		$this->set_db_properties();
		return $this->titel;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_artiest() {
		$this->set_db_properties();
		return $this->artiest;
	}
	
	/**
	 * 
	 * @return int|null
	 */
	public function get_jaar() {
		$this->set_db_properties();
		return $this->jaar;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_categorie() {
		$this->set_db_properties();
		return $this->categorie;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_map() {
		$this->set_db_properties();
		return $this->map;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function is_opener() {
		$this->set_db_properties();
		return $this->is_opener;
	}
	
	/**
	 * Geeft alle lijsten waar dit nummer op staat.
	 * @return Muzieklijst[]
	 */
	public function get_lijsten() {
		if ( $this->lijsten === null ) {
			$this->lijsten = [];
			$sql = sprintf(
				'SELECT lijst_id FROM muzieklijst_nummers_lijst WHERE nummer_id = %d',
				$this->get_id()
			);
			foreach ( Muzieklijsten_Database::selectSingleColumn($sql) as $lijst_id ) {
				$this->lijsten[] = new Muzieklijst($lijst_id);
			}
		}
		return $this->lijsten;
	}
	
	/**
	 * Vul het object met velden uit de database.
	 */
	private function set_db_properties() {
		if ( !$this->db_props_set ) {
			$entry = Muzieklijsten_Database::selectSingleRow(sprintf(
				'SELECT * FROM muzieklijst_nummers WHERE id = %d',
				$this->get_id()
			));
			$this->muziek_id = $entry['muziek_id'];
			$this->titel = $entry['titel'];
			$this->artiest = $entry['artiest'];
			$this->jaar = $entry['jaar'];
			$this->categorie = $entry['categorie'];
			$this->map = $entry['map'];
			$this->is_opener = $entry['opener'] == 1;
			$this->db_props_set = true;
		}
	}
}
