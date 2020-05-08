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
	/** @bar boolean */
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
	 * @return string
	 */
	public function get_naam() {
		$this->set_db_properties();
		return $this->naam;
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
