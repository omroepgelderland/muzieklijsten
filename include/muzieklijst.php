<?php

class Muzieklijst {
	
	/** @var int */
	private $id;
	/** @var boolean */
	private $actief;
	/** @var string */
	private $naam;
	/** @var int */
	private $minkeuzes;
	/** @var int */
	private $maxkeuzes;
	/** @var int */
	private $stemmen_per_ip;
	/** @var boolean */
	private $artiest_eenmalig;
	/** @var boolean */
	private $veld_telefoonnummer;
	/** @var boolean */
	private $veld_email;
	/** @var boolean */
	private $veld_woonplaats;
	/** @var boolean */
	private $veld_adres;
	/** @var boolean */
	private $veld_uitzenddatum;
	/** @var boolean */
	private $veld_vrijekeus;
	/** @var boolean */
	private $recaptcha;
	/** @var string[] */
	private $notificatie_email_adressen;
	/** @var Extra_Veld[] */
	private $extra_velden;
	/** @var Nummer[] */
	private $nummers;
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
	 * Geeft aan of twee muzieklijsten dezelfde zijn. Wanneer $obj geen Muzieklijst is wordt false gegeven.
	 * @param mixed $obj Object om deze instantie mee te vergelijken
	 * @return boolean Of $obj dezelfde muzieklijst is als deze instantie
	 */
	public function equals( $obj ) {
		return ( $obj instanceof Muzieklijst && $obj->get_id() == $this->id );
	}
	
	/**
	 * Geeft aan of de lijst actief is.
	 * @return type
	 */
	public function is_actief() {
		$this->set_db_properties();
		return $this->actief;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_naam() {
		$this->set_db_properties();
		return $this->naam;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function get_minkeuzes() {
		$this->set_db_properties();
		return $this->minkeuzes;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function get_maxkeuzes() {
		$this->set_db_properties();
		return $this->maxkeuzes;
	}
	
	/**
	 * 
	 * @return int|null
	 */
	public function get_max_stemmen_per_ip() {
		$this->set_db_properties();
		return $this->stemmen_per_ip;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function is_artiest_eenmalig() {
		$this->set_db_properties();
		return $this->artiest_eenmalig;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function heeft_veld_telefoonnummer() {
		$this->set_db_properties();
		return $this->veld_telefoonnummer;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function heeft_veld_email() {
		$this->set_db_properties();
		return $this->veld_email;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function heeft_veld_woonplaats() {
		$this->set_db_properties();
		return $this->veld_woonplaats;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function heeft_veld_adres() {
		$this->set_db_properties();
		return $this->veld_adres;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function heeft_veld_uitzenddatum() {
		$this->set_db_properties();
		return $this->veld_uitzenddatum;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function heeft_veld_vrijekeus() {
		$this->set_db_properties();
		return $this->veld_vrijekeus;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function heeft_gebruik_recaptcha() {
		$this->set_db_properties();
		return $this->recaptcha;
	}
	
	/**
	 * 
	 * @return string[]
	 */
	public function get_notificatie_email_adressen() {
		$this->set_db_properties();
		return $this->notificatie_email_adressen;
	}
	
	/**
	 * Geeft alle extra velden die bij de lijst horen.
	 * return Extra_Veld[]
	 */
	public function get_extra_velden() {
		if ( $this->extra_velden === null ) {
			$this->extra_velden = [];
			$sql = sprintf(
				'SELECT extra_veld_id, verplicht FROM muzieklijst_lijsten_extra_velden WHERE lijst_id = %d',
				$this->get_id()
			);
			foreach ( Muzieklijsten_Database::query($sql) as $entry ) {
				$id = $entry['extra_veld_id'];
				$verplicht = $entry['verplicht'] == 1;
				$this->extra_velden[] = new Extra_Veld($id, $verplicht);
			}
		}
		return $this->extra_velden;
	}
	
	/**
	 * Geeft alle nummers van deze lijst
	 * @return Nummer[]
	 */
	public function get_nummers() {
		if ( $this->nummers === null ) {
			$this->nummers = [];
			$sql = sprintf(
				'SELECT nummer_id FROM muzieklijst_nummers_lijst WHERE lijst_id = %d',
				$this->get_id()
			);
			foreach ( Muzieklijsten_Database::selectSingleColumn($sql) as $nummer_id ) {
				$this->nummers[] = new Nummer($nummer_id);
			}
		}
		return $this->nummers;
	}
	
	/**
	 * Geeft de stemmen op een nummer in de lijst
	 * @param Nummer $nummer
	 * @return Stem[] Stemmen
	 */
	public function get_stemmen( $nummer ) {
		$res = Muzieklijsten_Database::selectSingleColumn(sprintf(
			'SELECT id FROM muzieklijst_stemmen WHERE nummer_id = %d AND lijst_id = %d',
			$nummer->get_id(),
			$this->get_id()
		));
		$stemmen = [];
		foreach ( $res as $sid ) {
			$stemmen[] = new Stem($sid);
		}
		return $stemmen;
	}
	
	/**
	 * Zet alle klassevariabelen terug naar null, behalve het ID. Dit is nuttig wanneer de lijst is verwijderd of zodanig is aangepast dat de klassevariabelen niet meer overeenkomen met de database, en dus opnieuw moeten worden opgehaald.
	 * Wanneer er na de reset functies worden aangeroepen kunnen er twee dingen gebeuren:
	 * 1. Data wordt opnieuw opgehaald en is consistent met de database
	 * 2. Bij het ophalen van data wordt geconstateerd dat de lijst niet meer bestaat en een foutmelding gegeven.
	 */
	public function reset() {
		// Alle klassenvariabelen unsetten behalve ID
		foreach ( $this as $key => &$value ) {
			if ( $key != 'id' ) {
				$value = null;
			}
		}
		$this->db_props_set = false;
	}
	
	/**
	 * Verwijdert de lijst. Koppelingen met nummers, extra velden, stemmen e.d. worden ook verwijderd
	 */
	public function remove() {
		Muzieklijsten_Database::query(sprintf(
			'DELETE FROM muzieklijst_lijsten WHERE id = %d',
			$this->get_id()
		));
		$this->reset();
	}
	
	/**
	 * Vul het object met velden uit de database.
	 */
	private function set_db_properties() {
		if ( !$this->db_props_set ) {
			$entry = Muzieklijsten_Database::selectSingleRow(sprintf(
				'SELECT * FROM muzieklijst_lijsten WHERE id = %d',
				$this->get_id()
			));
			$this->actief = $entry['actief'] == 1;
			$this->naam = $entry['naam'];
			$this->minkeuzes = (int)$entry['minkeuzes'];
			$this->maxkeuzes = (int)$entry['maxkeuzes'];
			$this->stemmen_per_ip = $entry['stemmen_per_ip'];
			$this->artiest_eenmalig = $entry['artiest_eenmalig'] == 1;
			$this->veld_telefoonnummer = $entry['veld_telefoonnummer'] == 1;
			$this->veld_email = $entry['veld_email'] == 1;
			$this->veld_woonplaats = $entry['veld_woonplaats'] == 1;
			$this->veld_adres = $entry['veld_adres'] == 1;
			$this->veld_uitzenddatum = $entry['veld_uitzenddatum'] == 1;
			$this->veld_vrijekeus = $entry['veld_vrijekeus'] == 1;
			$this->recaptcha = $entry['recaptcha'] == 1;
			$this->notificatie_email_adressen = [];
			foreach ( explode(',', $entry['email']) as $adres ) {
				$adres = trim($adres);
				if ( strlen($adres) > 0 ) {
					$this->notificatie_email_adressen[] = $adres;
				}
			}
			$this->db_props_set = true;
		}
	}
}
