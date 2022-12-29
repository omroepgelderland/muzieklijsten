<?php

namespace muzieklijsten;

class Stemmer {
	
	private int $id;
	private ?string $naam;
	private ?string $adres;
	private ?string $postcode;
	private ?string $woonplaats;
	private ?string $telefoonnummer;
	private ?string $emailadres;
	private ?string $uitzenddatum;
	private ?string $vrijekeus;
	private string $ip;
	private \DateTime $tijdstip;
	private bool $db_props_set;
	
	/**
	 * @param int $id ID van het object.
	 * @param ?array $data Metadata uit de databasevelden (optioneel).
	 */
	public function __construct( int $id, ?array $data = null ) {
		$this->id = $id;
		$this->db_props_set = false;
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
	 * Geeft aan of twee stemmers dezelfde zijn. Wanneer $obj geen Stemmer is wordt false gegeven.
	 * @param mixed $obj Object om deze instantie mee te vergelijken
	 * @return bool Of $obj dezelfde stemmer is als deze instantie
	 */
	public function equals( $obj ): bool {
		return ( $obj instanceof Stemmer && $obj->get_id() == $this->id );
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_naam(): ?string {
		$this->set_db_properties();
		return $this->naam;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_adres(): ?string {
		$this->set_db_properties();
		return $this->adres;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_postcode(): ?string {
		$this->set_db_properties();
		return $this->postcode;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_woonplaats(): ?string {
		$this->set_db_properties();
		return $this->woonplaats;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_telefoonnummer(): ?string {
		$this->set_db_properties();
		return $this->telefoonnummer;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_emailadres(): ?string {
		$this->set_db_properties();
		return $this->emailadres;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_uitzenddatum(): ?string {
		$this->set_db_properties();
		return $this->uitzenddatum;
	}
	
	/**
	 * 
	 * @return string|null
	 */
	public function get_vrijekeus(): ?string {
		$this->set_db_properties();
		return $this->vrijekeus;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_ip(): string {
		$this->set_db_properties();
		return $this->ip;
	}
	
	/**
	 * 
	 * @return \DateTime
	 */
	public function get_tijdstip(): \DateTime {
		$this->set_db_properties();
		return $this->tijdstip;
	}
	
	/**
	 * Vul het object met velden uit de database.
	 */
	private function set_db_properties(): void {
		if ( !$this->db_props_set ) {
			$this->set_data(DB::selectSingleRow(sprintf(
				'SELECT * FROM stemmers WHERE id = %d',
				$this->get_id()
			)));
		}
	}

	/**
	 * Plaatst metadata in het object
	 * @param array $data Data.
	 */
	private function set_data( array $data ): void {
		$this->naam = $data['naam'];
		$this->adres = $data['adres'];
		$this->postcode = $data['postcode'];
		$this->woonplaats = $data['woonplaats'];
		$this->telefoonnummer = $data['telefoonnummer'];
		$this->emailadres = $data['emailadres'];
		$this->uitzenddatum = $data['uitzenddatum'];
		$this->vrijekeus = $data['vrijekeus'];
		$this->ip = $data['ip'];
		
		$this->tijdstip = $data['timestamp'];
		$this->db_props_set = true;
	}

	public static function maak(
		?string $naam,
		?string $adres,
		?string $postcode,
		?string $woonplaats,
		?string $telefoonnummer,
		?string $veld_email,
		?string $veld_uitzenddatum,
		?string $veld_vrijekeus,
		string $ip
	): Stemmer {
		try {
			$id = DB::insertMulti('stemmers', [
				'naam' => $naam,
				'adres' => $adres,
				'postcode' => $postcode,
				'woonplaats' => $woonplaats,
				'telefoonnummer' => $telefoonnummer,
				'emailadres' => $veld_email,
				'uitzenddatum' => $veld_uitzenddatum,
				'vrijekeus' => $veld_vrijekeus,
				'ip' => $ip
			]);
			return new self($id);
		} catch ( SQLException_DataTooLong ) {
			throw new GebruikersException('De invoer van een van de tekstvelden is te lang.');
		}
	}

	public function add_stem( Nummer $nummer, Lijst $lijst, string $toelichting ): Stem {
		try {
			DB::insertMulti('stemmen', [
				'nummer_id' => $nummer->get_id(),
				'lijst_id' => $lijst->get_id(),
				'stemmer_id' => $this->get_id(),
				'toelichting' => $toelichting
			]);
			return new Stem($nummer, $lijst, $this);
		} catch ( SQLException_DataTooLong ) {
			$max = DB::get_max_kolom_lengte('stemmen', 'toelichting');
			throw new GebruikersException("De toelichting bij \"{$nummer->get_titel()}\" is te lang. De maximale lengte is {$max} tekens.");
		}
	}
	
	/**
	 * Maakt een object uit een id aangeleverd door HTTP GET of POST.
	 * @param int $type INPUT_GET of INPUT_POST. Standaard is INPUT_POST.
	 * @return Stemmer
	 * @throws Muzieklijsten_Exception
	 */
	public static function maak_uit_request( int $type = INPUT_POST ): Stemmer {
		$id = filter_input($type, 'stemmer', FILTER_VALIDATE_INT);
		if ( $id === null ) {
			throw new Muzieklijsten_Exception('Geen stemmer in invoer');
		}
		if ( $id === false ) {
			throw new Muzieklijsten_Exception(sprintf(
				'Ongeldig stemmer id: %s',
				filter_input($type, 'stemmer')
			));
		}
		return new static($id);
	}

	/**
	 * Verwijdert de stemmer en al haar stemmen.
	 */
	public function verwijderen(): void {
		DB::query("DELETE FROM stemmers WHERE id = {$this->get_id()}");
		foreach ( $this as $key => $value ) {
			unset($this->$key);
		}
		$this->db_props_set = false;
	}

	/**
	 * Mailt een notificatie van deze stem naar de redactie.
	 * @param Lijst $lijst
	 */
	public function mail_redactie( Lijst $lijst ): void {

		$stemmer_gegevens = [];
		if ( $this->get_naam() !== null ) {
			$stemmer_gegevens[] = "Naam: {$this->get_naam()}";
		}
		if ( $this->get_adres() !== null ) {
			$stemmer_gegevens[] = "Adres: {$this->get_adres()}";
		}
		if ( $this->get_postcode() !== null ) {
			$stemmer_gegevens[] = "Postcode: {$this->get_postcode()}";
		}
		if ( $this->get_woonplaats() !== null ) {
			$stemmer_gegevens[] = "Woonplaats: {$this->get_woonplaats()}";
		}
		if ( $this->get_telefoonnummer() !== null ) {
			$stemmer_gegevens[] = "Telefoonnummer: {$this->get_telefoonnummer()}";
		}
		if ( $this->get_emailadres() !== null ) {
			$stemmer_gegevens[] = "E-mailadres: {$this->get_emailadres()}";
		}
		if ( $this->get_uitzenddatum() !== null ) {
			$stemmer_gegevens[] = "Uitzenddatum: {$this->get_uitzenddatum()}";
		}
		if ( $this->get_vrijekeus() !== null ) {
			$stemmer_gegevens[] = "Vrije keus: {$this->get_vrijekeus()}";
		}
		$stemmer_gegevens_str = implode("\n", $stemmer_gegevens);
		
		// Extra velden
		$extra_velden = [];
		foreach ( $lijst->get_extra_velden() as $extra_veld ) {
			try {
				$extra_velden[] = "{$extra_veld->get_label()}: {$extra_veld->get_stemmer_waarde($this)}";
			} catch ( Muzieklijsten_Exception $e ) {}
		}
		$extra_velden_str = implode("\n", $extra_velden);

		$nummers_lijst = [];
		foreach ( $this->get_stemmen($lijst) as $stem ) {
			$nummers_lijst[] = "{$stem->nummer->get_titel()} - {$stem->nummer->get_artiest()}";
			$nummers_lijst[] = "\tToelichting: {$stem->get_toelichting()}";
			$nummers_lijst[] = '';
		}
		$nummers_str = implode("\n", $nummers_lijst);

		$tekst_bericht = <<<EOT
		Ontvangen van:

		{$stemmer_gegevens_str}
		{$extra_velden_str}

		{$nummers_str}
		EOT;

		if ( $lijst->heeft_veld_uitzenddatum() ) {
			$onderwerp = "Er is gestemd - {$lijst->get_naam()} - Uitzenddatum: {$this->get_uitzenddatum()}";
		} else {
			$onderwerp = "Er is gestemd - {$lijst->get_naam()}";
		}

		if ( count($lijst->get_notificatie_email_adressen()) > 0 ) {
			stuur_mail(
				$lijst->get_notificatie_email_adressen(),
				[],
				Config::get_instelling('mail', 'afzender'),
				$onderwerp,
				$tekst_bericht
			);
		}

	}

	/**
	 * @return Stem[]
	 */
	public function get_stemmen( Lijst $lijst ): array {
		$query = <<<EOT
		SELECT *
		FROM stemmen
		WHERE
			lijst_id = {$lijst->get_id()}
			AND stemmer_id = {$this->get_id()}
		EOT;
		$stemmen = [];
		foreach ( DB::query($query) as $entry ) {
			$stemmen[] = new Stem(
				new Nummer($entry['nummer_id']),
				$lijst,
				$this,
				$entry
			);
		}
		return $stemmen;
	}

}
