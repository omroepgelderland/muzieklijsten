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
	/** @var DateTime */
	private $tijdstip;
	/** @var boolean */
	private $db_props_set;
	
	/**
	 * 
	 * @param int $id
	 */
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
	public function get_naam() {
		$this->set_db_properties();
		return $this->naam;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_adres() {
		$this->set_db_properties();
		return $this->adres;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_postcode() {
		$this->set_db_properties();
		return $this->postcode;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_woonplaats() {
		$this->set_db_properties();
		return $this->woonplaats;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_telefoonnummer() {
		$this->set_db_properties();
		return $this->telefoonnummer;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_emailadres() {
		$this->set_db_properties();
		return $this->emailadres;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_uitzenddatum() {
		$this->set_db_properties();
		return $this->uitzenddatum;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_vrijekeus() {
		$this->set_db_properties();
		return $this->vrijekeus;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_ip() {
		$this->set_db_properties();
		return $this->ip;
	}
	
	/**
	 * 
	 * @return DateTime
	 */
	public function get_tijdstip() {
		$this->set_db_properties();
		return $this->tijdstip;
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
			$this->tijdstip = new DateTime();
			$this->tijdstip->setTimestamp($entry['timestamp']);
			$this->db_props_set = true;
		}
	}
}
