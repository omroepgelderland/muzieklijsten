<?php

namespace muzieklijsten;

class Stem {

	public Nummer $nummer;
	public Lijst $lijst;
	public Stemmer $stemmer;
	private ?string $toelichting;
	private ?string $eigenkeuze;
	private bool $behandeld;
	private bool $db_props_set;
	
	/**
	 * @param Nummer $nummer
	 * @param Lijst $lijst
	 * @param Stemmer $stemmer
	 * @param ?array $data Metadata uit de databasevelden (optioneel).
	 */
	public function __construct(
		Nummer $nummer,
		Lijst $lijst,
		Stemmer $stemmer,
		?array $data = null
	) {
		$this->nummer = $nummer;
		$this->lijst = $lijst;
		$this->stemmer = $stemmer;
		$this->db_props_set = false;
		if ( isset($data) ) {
			$this->set_data($data);
		}
	}
	
	/**
	 * Geeft aan of twee stemmen dezelfde zijn. Wanneer $obj geen Stem is wordt false gegeven.
	 * @param mixed $obj Object om deze instantie mee te vergelijken
	 * @return bool Of $obj dezelfde stem is als deze instantie
	 */
	public function equals( $obj ): bool {
		return 
			$obj instanceof Stem
			&& $this->nummer->equals($obj->nummer)
			&& $this->lijst->equals($obj->lijst)
			&& $this->stemmer->equals($obj->stemmer);
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_toelichting(): ?string {
		$this->set_db_properties();
		return $this->toelichting;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_eigenkeuze(): ?string {
		$this->set_db_properties();
		return $this->eigenkeuze;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function is_behandeld(): bool {
		$this->set_db_properties();
		return $this->behandeld;
	}
	
	/**
	 * Vul het object met velden uit de database.
	 */
	private function set_db_properties(): void {
		if ( !$this->db_props_set ) {
			$query = <<<EOT
				SELECT *
				FROM stemmen
				WHERE {$this->get_where_voorwaarden()}
			EOT;
			$this->set_data(DB::selectSingleRow($query));
		}
	}


	/**
	 * Plaatst metadata in het object
	 * @param array $data Data.
	 */
	private function set_data( array $data ): void {
		$this->toelichting = $data['toelichting'];
		$this->eigenkeuze = $data['eigenkeuze'];
		$this->behandeld = $data['behandeld'] == 1;
		$this->db_props_set = true;
	}

	private function get_where_voorwaarden(): string {
		return <<<EOT
			nummer_id = {$this->nummer->get_id()}
			AND lijst_id = {$this->lijst->get_id()}
			AND stemmer_id = {$this->stemmer->get_id()}
		EOT;
	}

	/**
	 * Maakt een object uit een id aangeleverd door HTTP GET of POST.
	 * @param int $type INPUT_GET of INPUT_POST. Standaard is INPUT_POST.
	 * @return Stem
	 * @throws Muzieklijsten_Exception
	 */
	public static function maak_uit_request( int $type = INPUT_POST ): Stem {
		return new static(
			Nummer::maak_uit_request($type),
			Lijst::maak_uit_request($type),
			Stemmer::maak_uit_request($type)
		);
	}

	/**
	 * Stelt in of de stem al dan niet behandeld is.
	 * @param bool $waarde aan of uit.
	 */
	public function set_behandeld( bool $waarde ): void {
		DB::updateMulti('stemmen', [
			'behandeld' => $waarde
		], $this->get_where_voorwaarden());
	}

	/**
	 * Verwijdert de stem.
	 */
	public function verwijderen(): void {
		DB::query("DELETE FROM stemmen WHERE {$this->get_where_voorwaarden()}");
		verwijder_stemmers_zonder_stemmen();
		foreach ( $this as $key => $value ) {
			unset($this->$key);
		}
		$this->db_props_set = false;
	}

}
