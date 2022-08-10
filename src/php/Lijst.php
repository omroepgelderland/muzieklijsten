<?php

namespace muzieklijsten;

class Lijst {
	
	private int $id;
	private bool $actief;
	private string $naam;
	private int $minkeuzes;
	private int $maxkeuzes;
	private ?int $stemmen_per_ip;
	private bool $artiest_eenmalig;
	private bool $veld_telefoonnummer;
	private bool $veld_email;
	private bool $veld_woonplaats;
	private bool $veld_adres;
	private bool $veld_uitzenddatum;
	private bool $veld_vrijekeus;
	private bool $recaptcha;
	/** @var string[] */
	private array $notificatie_email_adressen;
	private string $bedankt_tekst;
	/** @var Extra_Veld[] */
	private array $extra_velden;
	/** @var Nummer[] */
	private array $nummers;
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
	 * Geeft aan of twee muzieklijsten dezelfde zijn. Wanneer $obj geen Muzieklijst is wordt false gegeven.
	 * @param mixed $obj Object om deze instantie mee te vergelijken
	 * @return bool Of $obj dezelfde muzieklijst is als deze instantie
	 */
	public function equals( $obj ): bool {
		return ( $obj instanceof Lijst && $obj->get_id() == $this->id );
	}
	
	/**
	 * Geeft aan of de lijst actief is.
	 * @return bool
	 */
	public function is_actief(): bool {
		$this->set_db_properties();
		return $this->actief;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function get_naam(): string {
		$this->set_db_properties();
		return $this->naam;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function get_minkeuzes(): int {
		$this->set_db_properties();
		return $this->minkeuzes;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function get_maxkeuzes(): int {
		$this->set_db_properties();
		return $this->maxkeuzes;
	}
	
	/**
	 * 
	 * @return ?int
	 */
	public function get_max_stemmen_per_ip(): ?int {
		$this->set_db_properties();
		return $this->stemmen_per_ip;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function is_artiest_eenmalig(): bool {
		$this->set_db_properties();
		return $this->artiest_eenmalig;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_telefoonnummer(): bool {
		$this->set_db_properties();
		return $this->veld_telefoonnummer;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_email(): bool {
		$this->set_db_properties();
		return $this->veld_email;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_woonplaats(): bool {
		$this->set_db_properties();
		return $this->veld_woonplaats;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_adres(): bool {
		$this->set_db_properties();
		return $this->veld_adres;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_uitzenddatum(): bool {
		$this->set_db_properties();
		return $this->veld_uitzenddatum;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_vrijekeus(): bool {
		$this->set_db_properties();
		return $this->veld_vrijekeus;
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_gebruik_recaptcha(): bool {
		$this->set_db_properties();
		return $this->recaptcha;
	}
	
	/**
	 * 
	 * @return string[]
	 */
	public function get_notificatie_email_adressen(): array {
		$this->set_db_properties();
		return $this->notificatie_email_adressen;
	}
	
	/**
	 * Geeft alle extra velden die bij de lijst horen.
	 * return Extra_Veld[]
	 */
	public function get_extra_velden(): array {
		if ( !isset($this->extra_velden) ) {
			$this->extra_velden = [];
			$query = <<<EOT
				SELECT extra_veld_id, verplicht
				FROM lijsten_extra_velden
				WHERE lijst_id = {$this->get_id()}
			EOT;
			foreach ( DB::query($query) as $entry ) {
				$id = $entry['extra_veld_id'];
				$verplicht = $entry['verplicht'] == 1;
				$this->extra_velden[] = new Extra_Veld($id, null, $verplicht);
			}
		}
		return $this->extra_velden;
	}
	
	/**
	 * Geeft alle nummers van deze lijst
	 * @return Nummer[]
	 */
	public function get_nummers(): array {
		if ( !isset($this->nummers) ) {
			$this->nummers = [];
			$sql = sprintf(
				'SELECT nummer_id FROM lijsten_nummers WHERE lijst_id = %d',
				$this->get_id()
			);
			foreach ( DB::selectSingleColumn($sql) as $nummer_id ) {
				$this->nummers[] = new Nummer($nummer_id);
			}
		}
		return $this->nummers;
	}
	
	/**
	 * Geeft alle nummers van deze lijst gesorteerd op titel.
	 * @return Nummer[]
	 */
	public function get_nummers_sorteer_titels(): array {
		$nummers = [];
		$sql = <<<EOT
			SELECT n.*
			FROM nummers n
			INNER JOIN lijsten_nummers nl ON
				nl.lijst_id = {$this->get_id()}
				AND nl.nummer_id = n.id
			ORDER BY n.titel
		EOT;
		foreach ( DB::query($sql) as $entry ) {
			$nummers[] = new Nummer($entry['id'], $entry);
		}
		return $nummers;
	}
	
	/**
	 * Geeft de stemmen op alle nummers in de lijst.
	 * @param ?Nummer $nummer Neem alleen de stemmen op dit nummer mee (optioneel).
	 * @param \DateTime $van Neem alleen de stemmen vanaf deze datum (optioneel).
	 * @param \DateTime $tot Neem alleen de stemmen tot en met deze datum (optioneel).
	 * @return Stem[] Stemmen
	 */
	public function get_stemmen(
		?Nummer $nummer = null,
		?\DateTime $van = null,
		?\DateTime $tot = null
	): array {
		$where = ["sm.lijst_id = {$this->get_id()}"];
		if ( isset($nummer) ) {
			$where[] = "sm.nummer_id = {$nummer->get_id()}";
		}
		$on = [
			'st.id = sm.stemmer_id'
		];
		if ( isset($van) ) {
			$on[] = "DATE(st.timestamp) >= \"{$van->format('Y-m-d')}\"";
		}
		if ( isset($tot) ) {
			$on[] = "DATE(st.timestamp) <= \"{$tot->format('Y-m-d')}\"";
		}
		$where_str = implode(' AND ', $where);
		$on_str = implode(' AND ', $on);
		$query = <<<EOT
			SELECT sm.*
			FROM stemmen sm
			INNER JOIN stemmers st ON {$on_str}
			WHERE
				{$where_str}
		EOT;
		$stemmen = [];
		foreach ( DB::query($query) as $entry ) {
			$stem_nummer = isset($nummer)
				? $nummer
				: new Nummer($entry['nummer_id']);
			$stemmen[] = new Stem(
				$stem_nummer,
				$this,
				new Stemmer($entry['stemmer_id']),
				$entry
			);
		}
		return $stemmen;
	}

	/**
	 * Geeft alle unieke stemmers.
	 * @param \DateTime $van Neem alleen de stemmers die gestemd hebben vanaf deze datum (optioneel).
	 * @param \DateTime $tot Neem alleen de stemmers die gestemd hebben tot en met deze datum (optioneel).
	 */
	public function get_stemmers( ?\DateTime $van = null, ?\DateTime $tot = null ): array {
		$where = [];
		if ( isset($van) ) {
			$where[] = "DATE(timestamp) >= \"{$van->format('Y-m-d')}\"";
		}
		if ( isset($tot) ) {
			$where[] = "DATE(timestamp) <= \"{$tot->format('Y-m-d')}\"";
		}
		$where_str = implode(' AND ', $where);
		if ( count($where) > 0 ) {
			$query = <<<EOT
				SELECT stemmer_id
				FROM stemmen
				WHERE
					lijst_id = {$this->get_id()}
					AND stemmer_id IN (
						SELECT id
						FROM stemmers
						WHERE {$where_str}
					)
				GROUP BY stemmer_id
			EOT;
		} else {
			$query = <<<EOT
				SELECT stemmer_id
				FROM stemmen
				WHERE
					lijst_id = {$this->get_id()}
				GROUP BY stemmer_id
			EOT;
		}
		return DB::selectObjectLijst($query, Stemmer::class);
	}

	/**
	 * Geeft alle nummers uit de lijst gesorteerd op het aantal stemmen (hoogste aantal eerst).
	 * @param \DateTime $van Neem alleen de stemmen vanaf deze datum (optioneel).
	 * @param \DateTime $tot Neem alleen de stemmen tot en met deze datum (optioneel).
	 * @return Nummer[]
	 */
	public function get_nummers_volgorde_aantal_stemmen(
		?\DateTime $van = null,
		?\DateTime $tot = null
	): array {
		$datumvoorwaarden = [];
		if ( isset($van) ) {
			$datumvoorwaarden[] = "DATE(timestamp) >= \"{$van->format('Y-m-d')}\"";
		}
		if ( isset($tot) ) {
			$datumvoorwaarden[] = "DATE(timestamp) <= \"{$tot->format('Y-m-d')}\"";
		}
		$datumvoorwaarden_str = implode(' AND ', $datumvoorwaarden);
		if ( count($datumvoorwaarden) > 0 ) {
			$datumvoorwaarden_query = <<<EOT
				AND sm.stemmer_id IN (
					SELECT id
					FROM stemmers
					WHERE {$datumvoorwaarden_str}
				)
			EOT;
		} else {
			$datumvoorwaarden_query = '';
		}
		$query = <<<EOT
			SELECT nl.nummer_id
			FROM lijsten_nummers nl
			LEFT JOIN stemmen sm ON
				sm.nummer_id = nl.nummer_id
				AND sm.lijst_id = nl.lijst_id
				{$datumvoorwaarden_query}
			WHERE
				nl.lijst_id = {$this->get_id()}
			GROUP BY nl.nummer_id
			ORDER BY COUNT(sm.stemmer_id) DESC
		EOT;
		return DB::selectObjectLijst($query, Nummer::class);
	}
	
	/**
	 * Geeft de tekst die stemmers te zien krijgen na het uitbrengen van een stem.
	 * @return string
	 */
	public function get_bedankt_tekst(): string {
		$this->set_db_properties();
		return $this->bedankt_tekst;
	}
	
	/**
	 * Zet alle klassevariabelen terug naar null, behalve het ID. Dit is nuttig wanneer de lijst is verwijderd of zodanig is aangepast dat de klassevariabelen niet meer overeenkomen met de database, en dus opnieuw moeten worden opgehaald.
	 * Wanneer er na de reset functies worden aangeroepen kunnen er twee dingen gebeuren:
	 * 1. Data wordt opnieuw opgehaald en is consistent met de database
	 * 2. Bij het ophalen van data wordt geconstateerd dat de lijst niet meer bestaat en een foutmelding gegeven.
	 */
	public function reset(): void {
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
	public function remove(): void {
		DB::query(sprintf(
			'DELETE FROM lijsten WHERE id = %d',
			$this->get_id()
		));
		$this->reset();
	}
	
	/**
	 * Vul het object met velden uit de database.
	 */
	private function set_db_properties(): void {
		if ( !$this->db_props_set ) {
			$this->set_data(DB::selectSingleRow(sprintf(
				'SELECT * FROM lijsten WHERE id = %d',
				$this->get_id()
			)));
		}
	}

	/**
	 * Plaatst metadata in het object
	 * @param array $data Data.
	 */
	private function set_data( array $data ): void {
		$this->actief = $data['actief'] == 1;
		$this->naam = $data['naam'];
		$this->minkeuzes = $data['minkeuzes'];
		$this->maxkeuzes = $data['maxkeuzes'];
		$this->stemmen_per_ip = $data['stemmen_per_ip'];
		$this->artiest_eenmalig = $data['artiest_eenmalig'] == 1;
		$this->veld_telefoonnummer = $data['veld_telefoonnummer'] == 1;
		$this->veld_email = $data['veld_email'] == 1;
		$this->veld_woonplaats = $data['veld_woonplaats'] == 1;
		$this->veld_adres = $data['veld_adres'] == 1;
		$this->veld_uitzenddatum = $data['veld_uitzenddatum'] == 1;
		$this->veld_vrijekeus = $data['veld_vrijekeus'] == 1;
		$this->recaptcha = $data['recaptcha'] == 1;
		$this->notificatie_email_adressen = [];
		foreach ( explode(',', $data['email']) as $adres ) {
			$adres = trim($adres);
			if ( strlen($adres) > 0 ) {
				$this->notificatie_email_adressen[] = $adres;
			}
		}
		$this->bedankt_tekst = $data['bedankt_tekst'];
		$this->db_props_set = true;
	}

	/**
	 * Maakt een nieuwe inactieve lijst met dezelfde eigenschappen als deze en verplaatst alle stemmen op deze lijst daar naartoe.
	 * @param string $naam Naam van de nieuwe lijst.
	 * @return Lijst
	 */
	public function dupliceer( string $naam ): Lijst {
		// Nieuwe lijst maken.
		$query = <<<EOT
			INSERT INTO lijsten
				(
					actief,
					naam,
					minkeuzes,
					maxkeuzes,
					stemmen_per_ip,
					artiest_eenmalig,
					veld_telefoonnummer,
					veld_email,
					veld_woonplaats,
					veld_adres,
					veld_uitzenddatum,
					veld_vrijekeus,
					recaptcha,
					email,
					bedankt_tekst
				)
				SELECT
					0,
					"{$naam}",
					minkeuzes,
					maxkeuzes,
					stemmen_per_ip,
					artiest_eenmalig,
					veld_telefoonnummer,
					veld_email,
					veld_woonplaats,
					veld_adres,
					veld_uitzenddatum,
					veld_vrijekeus,
					recaptcha,
					email,
					bedankt_tekst
				FROM lijsten
				WHERE id = {$this->get_id()}
		EOT;
		DB::query($query);
		$nieuw_id = DB::getDB()->insert_id;

		// Extra velden koppelen
		$query = <<<EOT
			INSERT INTO lijsten_extra_velden
				(lijst_id, extra_veld_id, verplicht)
				SELECT
					{$nieuw_id},
					extra_veld_id,
					verplicht
				FROM lijsten_extra_velden
				WHERE lijst_id = {$this->get_id()}
		EOT;
		DB::query($query);

		// Nummers koppelen
		$query = <<<EOT
			INSERT INTO lijsten_nummers
				(nummer_id, lijst_id)
				SELECT nummer_id, {$nieuw_id}
				FROM lijsten_nummers
				WHERE lijst_id = {$this->get_id()}
		EOT;
		DB::query($query);

		// Stemmen verplaatsen
		$query = <<<EOT
			UPDATE stemmen s
			JOIN stemmers r ON
				s.stemmer_id = r.id
			SET s.lijst_id = {$nieuw_id}
			WHERE
				s.lijst_id = {$this->get_id()};
		EOT;
		DB::query($query);

		return new Lijst($nieuw_id);
	}

	/**
	 * Maakt een object uit een id aangeleverd door HTTP GET of POST.
	 * @param int $type INPUT_GET of INPUT_POST. Standaard is INPUT_POST.
	 * @return Lijst
	 * @throws Muzieklijsten_Exception
	 */
	public static function maak_uit_request( int $type = INPUT_POST ): Lijst {
		$id = filter_input($type, 'lijst', FILTER_VALIDATE_INT);
		if ( $id === null ) {
			throw new Muzieklijsten_Exception('Geen lijst in invoer');
		}
		if ( $id === false ) {
			throw new Muzieklijsten_Exception(sprintf(
				'Ongeldige muzieklijst id: %s',
				filter_input($type, 'lijst')
			));
		}
		return new static($id);
	}

	public function is_max_stemmen_per_ip_bereikt(): bool {
		$query = <<<EOT
			SELECT
				l.stemmen_per_ip IS NOT NULL
					AND COUNT(DISTINCT stemmers.id) >= l.stemmen_per_ip
			FROM stemmers stemmers
			INNER JOIN stemmen stemmen ON
				stemmen.stemmer_id = stemmers.id
			INNER JOIN lijsten l ON
				l.id = {$this->get_id()}
				AND stemmen.lijst_id = l.id
			WHERE stemmers.ip = "{$_SERVER['REMOTE_ADDR']}"
		EOT;
		return DB::selectSingle($query) == 1;
	}

	/**
	 * Haalt een nummer weg uit de stemlijst.
	 * Het nummer blijft bestaan in de database; het is alleen niet meer aan deze lijst gekoppeld.
	 * Alle stemmen op dit nummer in deze lijst worden verwijderd.
	 * Stemmers die geen stemmen meer hebben worden verwijderd.
	 * @param Nummer $nummer
	 */
	public function verwijder_nummer( Nummer $nummer ): void {
		// Verwijder de koppeling en de stemmen.
		$query = <<<EOT
			DELETE nl, s
			FROM lijsten_nummers nl
			LEFT JOIN stemmen s ON
				s.nummer_id = nl.nummer_id
				AND s.lijst_id = nl.lijst_id
			WHERE
				nl.nummer_id = {$nummer->get_id()}
				AND nl.lijst_id = {$this->get_id()}
		EOT;
		DB::query($query);
		verwijder_stemmers_zonder_stemmen();
	}

	/**
	 * Verwijdert de lijst.
	 */
	public function verwijderen(): void {
		DB::query("DELETE FROM lijsten WHERE id = {$this->get_id()}");
		verwijder_stemmers_zonder_stemmen();
		foreach ( $this as $key => $value ) {
			unset($this->$key);
		}
		$this->db_props_set = false;
	}

}
