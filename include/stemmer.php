<?php

class Stemmer {
	
	/** @var int */
	private $id;
	/** @var string */
	private $naam;
	/** @var string */
	private $adres;
	/** @var string */
	private $postcode;
	/** @var string */
	private $woonplaats;
	/** @var string */
	private $telefoonnummer;
	/** @var string */
	private $emailadres;
	/** @var string */
	private $uitzenddatum;
	/** @var string */
	private $vrijekeus;
	/** @var string */
	private $ip;
	/** @var int */
	private $timestamp;
	/** @var boolean */
	private $db_props_set;
	
	/**
	 * 
	 * @param int $id
	 */
	public function __construct( $id ) {
		$this->id = $id;
	}
	
	public function get_id() {
		return $this->id;
	}
	
	/**
	 * Vul het object met velden uit de database.
	 */
	private function set_db_properties() {
		if ( !$this->db_props_set ) {
			$entry = Muzieklijsten_Database::selectSingleRow(sprintf(
				'SELECT * FROM muzieklijst_stemmers WHERE id = %d',
				$this->get_id()
			));
			$this->naam = $entry['naam'];
			$this->adres = $entry['adres'];
			$this->postcode = $entry['postcode'];
			$this->woonplaats = $entry['woonplaats'];
			$this->telefoonnummer = $entry['telefoonnummer'];
			$this->emailadres = $entry['emailadres'];
			$this->uitzenddatum = $entry['uitzenddatum'];
			$this->vrijekeus = $entry['vrijekeus'];
			$this->ip = $entry['ip'];
			$this->timestamp = $entry['timestamp'];
			$this->db_props_set = true;
		}
	}
}
