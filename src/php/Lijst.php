<?php

namespace muzieklijsten;

class Lijst {

	public const VELD_ZICHTBAAR_BIT = 0;
	public const VELD_VERPLICHT_BIT = 1;
	
	private int $id;
	private bool $actief;
	private string $naam;
	private int $minkeuzes;
	private int $maxkeuzes;
	private ?int $stemmen_per_ip;
	private bool $artiest_eenmalig;
	private int $veld_telefoonnummer;
	private int $veld_email;
	private int $veld_woonplaats;
	private int $veld_adres;
	private int $veld_uitzenddatum;
	private int $veld_vrijekeus;
	private bool $recaptcha;
	/** @var string[] */
	private array $notificatie_email_adressen;
	private string $bedankt_tekst;
	/** @var Veld[] */
	private array $velden;
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
	 * Geeft aan of het veld "naam" in het formulier staat.
	 * @return bool
	 */
	public function heeft_veld_naam(): bool {
		return true;
	}

	/**
	 * Geeft aan of het veld "naam" verplicht is.
	 * @return bool
	 */
	public function is_veld_naam_verplicht(): bool {
		return true;
	}

	/**
	 * Geeft de HTML van het invoerveld voor de naam.
	 * Dit is leeg als in de lijst is ingesteld dat dit veld niet getoond wordt.
	 * @return string
	 * @deprecated
	 */
	public function get_veld_naam_html(): string {
		if ( $this->heeft_veld_naam() ) {
			return $this->get_formulier_html(
				'naam',
				'Naam',
				'text',
				'Vul uw naam in a.u.b.',
				$this->is_veld_naam_verplicht(),
				100
			);
		} else {
			return '';
		}
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_telefoonnummer(): bool {
		$this->set_db_properties();
		return ($this->veld_telefoonnummer & (1 << self::VELD_ZICHTBAAR_BIT)) > 0;
	}

	/**
	 * Geeft aan of het veld "telefoonnummer" verplicht is.
	 * @return bool
	 */
	public function is_veld_telefoonnummer_verplicht(): bool {
		$this->set_db_properties();
		return ($this->veld_telefoonnummer & (1 << self::VELD_VERPLICHT_BIT)) > 0;
	}
	
	/**
	 * Geeft de HTML van het invoerveld voor het telefoonnummer.
	 * Dit is leeg als in de lijst is ingesteld dat dit veld niet getoond wordt.
	 * @return string
	 * @deprecated
	 */
	public function get_veld_telefoonnummer_html(): string {
		if ( $this->heeft_veld_telefoonnummer() ) {
			return $this->get_formulier_html(
				'telefoonnummer',
				'Telefoonnummer',
				'tel',
				'Vul uw telefoonnummer in a.u.b.',
				$this->is_veld_telefoonnummer_verplicht(),
				100
			);
		} else {
			return '';
		}
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_email(): bool {
		$this->set_db_properties();
		return ($this->veld_email & (1 << self::VELD_ZICHTBAAR_BIT)) > 0;
	}

	/**
	 * Geeft aan of het veld "e-mail" verplicht is.
	 * @return bool
	 */
	public function is_veld_email_verplicht(): bool {
		$this->set_db_properties();
		return ($this->veld_email & (1 << self::VELD_VERPLICHT_BIT)) > 0;
	}
	
	/**
	 * Geeft de HTML van het invoerveld voor het e-mailadres.
	 * Dit is leeg als in de lijst is ingesteld dat dit veld niet getoond wordt.
	 * @return string
	 * @deprecated
	 */
	public function get_veld_email_html(): string {
		if ( $this->heeft_veld_email() ) {
			return $this->get_formulier_html(
				'veld_email',
				'E-mailadres',
				'email',
				'Vul uw e-mailadres in a.u.b.',
				$this->is_veld_email_verplicht(),
				100
			);
		} else {
			return '';
		}
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_woonplaats(): bool {
		$this->set_db_properties();
		return ($this->veld_woonplaats & (1 << self::VELD_ZICHTBAAR_BIT)) > 0;
	}

	/**
	 * Geeft aan of het veld "woonplaats" verplicht is.
	 * @return bool
	 */
	public function is_veld_woonplaats_verplicht(): bool {
		$this->set_db_properties();
		return ($this->veld_woonplaats & (1 << self::VELD_VERPLICHT_BIT)) > 0;
	}
	
	/**
	 * Geeft de HTML van het invoerveld voor de woonplaats.
	 * Dit is leeg als in de lijst is ingesteld dat dit veld niet getoond wordt.
	 * @return string
	 * @deprecated
	 */
	public function get_veld_woonplaats_html(): string {
		if ( $this->heeft_veld_adres() || $this->heeft_veld_woonplaats() ) {
			return $this->get_formulier_html(
				'woonplaats',
				'Woonplaats',
				'text',
				'Vul uw woonplaats in a.u.b.',
				$this->is_veld_adres_verplicht() || $this->is_veld_woonplaats_verplicht(),
				100
			);
		} else {
			return '';
		}
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_adres(): bool {
		$this->set_db_properties();
		return ($this->veld_adres & (1 << self::VELD_ZICHTBAAR_BIT)) > 0;
	}

	/**
	 * Geeft aan of het veld "adres" verplicht is.
	 * @return bool
	 */
	public function is_veld_adres_verplicht(): bool {
		$this->set_db_properties();
		return ($this->veld_adres & (1 << self::VELD_VERPLICHT_BIT)) > 0;
	}

	/**
	 * Geeft de HTML van het invoerveld voor het adres.
	 * Dit is leeg als in de lijst is ingesteld dat dit veld niet getoond wordt.
	 * @return string
	 * @deprecated
	 */
	public function get_veld_adres_html(): string {
		if ( $this->heeft_veld_adres() ) {
			return $this->get_formulier_html(
				'adres',
				'Adres',
				'text',
				'Vul uw adres in a.u.b.',
				$this->is_veld_adres_verplicht(),
				100
			)
			.$this->get_formulier_html(
				'postcode',
				'Postcode',
				'text',
				'Vul uw postcode in a.u.b.',
				$this->is_veld_adres_verplicht(),
				100
			);
		} else {
			return '';
		}
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_uitzenddatum(): bool {
		$this->set_db_properties();
		return ($this->veld_uitzenddatum & (1 << self::VELD_ZICHTBAAR_BIT)) > 0;
	}

	/**
	 * Geeft aan of het veld "uitzenddatum" verplicht is.
	 * @return bool
	 */
	public function is_veld_uitzenddatum_verplicht(): bool {
		$this->set_db_properties();
		return ($this->veld_uitzenddatum & (1 << self::VELD_VERPLICHT_BIT)) > 0;
	}
	
	/**
	 * Geeft de HTML van het invoerveld voor de uitzenddatum.
	 * Dit is leeg als in de lijst is ingesteld dat dit veld niet getoond wordt.
	 * @return string
	 * @deprecated
	 */
	public function get_veld_uitzenddatum_html(): string {
		if ( $this->heeft_veld_uitzenddatum() ) {	
			$template = <<<EOT
			<div class="form-group">
				<label class="control-label col-sm-2">Uitzenddatum</label>
				<div class="col-sm-10">
					<input type="date" class="form-control" placeholder="selecteer een datum" data-leeg-feedback="Vul de uitzenddatum in a.u.b.">
				</div>
			</div>
			EOT;
			$id = 'veld_uitzenddatum';
			$doc = new HTMLTemplate($template);
			/** @var \DOMElement */
			$e_label = $doc->getElementsByTagName('label')->item(0);
			/** @var \DOMElement */
			$e_input = $doc->getElementsByTagName('input')->item(0);
			$e_label->setAttribute('for', $id);
			$e_input->setAttribute('id', $id);
			$e_input->setAttribute('name', $id);
			if ( $this->is_veld_uitzenddatum_verplicht() ) {
				$e_input->appendChild($doc->createAttribute('required'));
			}
			return $doc->saveHTML();
		} else {
			return '';
		}
	}
	
	/**
	 * 
	 * @return bool
	 */
	public function heeft_veld_vrijekeus(): bool {
		$this->set_db_properties();
		return ($this->veld_vrijekeus & (1 << self::VELD_ZICHTBAAR_BIT)) > 0;
	}

	/**
	 * Geeft aan of het veld "vrije keuze" verplicht is.
	 * @return bool
	 */
	public function is_veld_vrijekeus_verplicht(): bool {
		$this->set_db_properties();
		return ($this->veld_vrijekeus & (1 << self::VELD_VERPLICHT_BIT)) > 0;
	}
	
	/**
	 * Geeft de HTML van het invoerveld voor de vrije keuze.
	 * Dit is leeg als in de lijst is ingesteld dat dit veld niet getoond wordt.
	 * @return string
	 * @deprecated
	 */
	public function get_veld_vrijekeus_html(): string {
		if ( $this->heeft_veld_vrijekeus() ) {
			return $this->get_formulier_html(
				'veld_vrijekeus',
				'Vrije keuze',
				'text',
				'Vul een eigen keuze in a.u.b.',
				$this->is_veld_vrijekeus_verplicht(),
				null,
				'Vul hier je eigen favoriet in.'
			);
		} else {
			return '';
		}
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
	 * Geeft alle velden die bij de lijst horen.
	 * @return Veld[]
	 */
	public function get_velden(): array {
		if ( !isset($this->velden) ) {
			$this->velden = [];
			$query = <<<EOT
				SELECT veld_id, verplicht
				FROM lijsten_velden
				WHERE lijst_id = {$this->get_id()}
				ORDER BY veld_id
			EOT;
			foreach ( DB::query($query) as $entry ) {
				$id = $entry['veld_id'];
				$verplicht = $entry['verplicht'] == 1;
				$this->velden[] = new Veld($id, null, $verplicht);
			}
		}
		return $this->velden;
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
	 * @param Nummer|null $nummer Neem alleen de stemmen op dit nummer mee (optioneel).
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
	 * Geeft alle nummers met ten minste één stem uit de lijst gesorteerd op het
	 * aantal stemmen (hoogste aantal eerst).
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
			HAVING COUNT(sm.stemmer_id) > 0
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
	 * Verwijdert de lijst. Koppelingen met nummers, velden, stemmen e.d. worden ook verwijderd
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
		$this->veld_telefoonnummer = $data['veld_telefoonnummer'];
		$this->veld_email = $data['veld_email'];
		$this->veld_woonplaats = $data['veld_woonplaats'];
		$this->veld_adres = $data['veld_adres'];
		$this->veld_uitzenddatum = $data['veld_uitzenddatum'];
		$this->veld_vrijekeus = $data['veld_vrijekeus'];
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

		// Velden koppelen
		$query = <<<EOT
			INSERT INTO lijsten_velden
				(lijst_id, veld_id, verplicht)
				SELECT
					{$nieuw_id},
					veld_id,
					verplicht
				FROM lijsten_velden
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
	 * @throws GeenLijstException
	 */
	public static function maak_uit_request( int $type = INPUT_POST ): Lijst {
		$id = filter_input($type, 'lijst', FILTER_VALIDATE_INT);
		if ( $id === null ) {
			throw new GeenLijstException('Geen lijst in invoer');
		}
		if ( $id === false ) {
			throw new GeenLijstException(sprintf(
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
	 * Voegt een nummer toe aan de stemlijst.
	 * @param Nummer $nummer
	 */
	public function nummer_toevoegen( Nummer $nummer ): void {
		DB::insertMulti('lijsten_nummers', [
			'nummer_id' => $nummer->get_id(),
			'lijst_id' => $this->get_id()
		]);
		if ( isset($this->nummers) ) {
			$this->nummers[] = $nummer;
		}
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
	
	/**
	 * Maakt HTML voor een invoerveld in het formulier.
	 * @param string $id ID en naam van het veld.
	 * @param string $label Zichtbaar label.
	 * @param string $type Type van <input>
	 * @param string $leeg_feedback Tekst die aan de gebruiker wordt getoond
	 * als het verplichte veld niet is ingevuld.
	 * @param bool $is_verplicht Of het veld verplicht is.
	 * @param int|null $max_length Maximale lengte van tekstinvoer (optioneel).
	 * @param string $placeholder Placeholdertekst (optioneel).
	 * @return string HTML.
	 */
	private function get_formulier_html(
		string $id,
		string $label,
		string $type,
		string $leeg_feedback,
		bool $is_verplicht,
		?int $max_length = null,
		string $placeholder = ''
	): string {
		$template = <<<EOT
		<div class="form-group row">
			<label class="control-label col-sm-2"></label>
			<div class="col-sm-10">
				<input class="form-control">
			</div>
		</div>
		EOT;
		$doc = new HTMLTemplate($template);
		/** @var \DOMElement */
		$e_label = $doc->getElementsByTagName('label')->item(0);
		/** @var \DOMElement */
		$e_input = $doc->getElementsByTagName('input')->item(0);
		$e_label->appendChild($doc->createTextNode($label));
		$e_label->setAttribute('for', $id);
		$e_input->setAttribute('type', $type);
		$e_input->setAttribute('id', $id);
		$e_input->setAttribute('name', $id);
		if ( strlen($placeholder) > 0 ){
			$e_input->setAttribute('placeholder', $placeholder);
		}
		$e_input->setAttribute('data-leeg-feedback', $leeg_feedback);
		if ( isset($max_length) && in_array($type, ['text', 'search', 'url', 'tel', 'email', 'password']) ) {
			$e_input->setAttribute('maxlength', $max_length);
		}
		if ( $is_verplicht ) {
			$e_input->appendChild($doc->createAttribute('required'));
		}
		return $doc->saveHTML();
	}

	public function set_veld( Veld $veld, bool $verplicht ): void {
		DB::insert_update_multi('lijsten_velden', [
			'lijst_id' => $this->get_id(),
			'veld_id' => $veld->get_id(),
			'verplicht' => $verplicht
		]);
	}

	public function remove_veld( Veld $veld ): void {
		$query = <<<EOT
		DELETE
		FROM lijsten_velden
		WHERE
			lijst_id = {$this->get_id()}
			AND veld_id = {$veld->get_id()}
		EOT;
		DB::query($query);
	}

	public function get_resultaten(): array {
		$query = <<<EOT
		SELECT
			stemmen.nummer_id,
			n.titel,
			n.artiest,
			stemmers.id AS stemmer_id,
			stemmers.ip,
			stemmen.behandeld,
			stemmen.toelichting,
			stemmers.timestamp,
			stemmers.is_geanonimiseerd,
			v.id AS veld_id,
			v.type,
			sv.waarde
		FROM stemmen
		INNER JOIN nummers n ON
			n.id = stemmen.nummer_id
		INNER JOIN stemmers ON
			stemmers.id = stemmen.stemmer_id
		INNER JOIN lijsten_velden lv ON
			lv.lijst_id = {$this->get_id()}
		INNER JOIN velden v ON
			v.id = lv.veld_id
		LEFT JOIN stemmers_velden sv ON
			sv.stemmer_id = stemmers.id
			AND sv.veld_id = v.id
		INNER JOIN (
			SELECT
				nummer_id,
				COUNT(nummer_id) AS aantal
			FROM stemmen
			WHERE lijst_id = {$this->get_id()}
			GROUP BY nummer_id
		) a ON
			a.nummer_id = stemmen.nummer_id
		WHERE
			stemmen.lijst_id = {$this->get_id()}
		ORDER BY
			a.aantal DESC,
			n.id,
			stemmers.timestamp ASC,
			stemmers.id,
			v.id,
			RAND()
		EOT;
		$nummers = [];
		foreach ( DB::query($query) as [
			'nummer_id' => $nummer_id,
			'titel' => $titel,
			'artiest' => $artiest,
			'stemmer_id' => $stemmer_id,
			'ip' => $ip,
			'behandeld' => $is_behandeld,
			'toelichting' => $toelichting,
			'timestamp' => $timestamp,
			'is_geanonimiseerd' => $is_geanonimiseerd,
			'veld_id' => $veld_id,
			'type' => $type,
			'waarde' => $waarde
		]) {
			$nummer_id = (int)$nummer_id;
			$stemmer_id = (int)$stemmer_id;
			$is_behandeld = $is_behandeld == 1;
			$is_geanonimiseerd = $is_geanonimiseerd == 1;
			$veld_id = (int)$veld_id;
			if ( $is_geanonimiseerd ) {
				$ip = $toelichting = $waarde = '🔒';
				$type = 'text';
			}

			$nummers[$nummer_id]['nummer'] = [
				'id' => $nummer_id,
				'titel' => $titel,
				'artiest' => $artiest
			];
			$nummers[$nummer_id]['stemmen'][$stemmer_id]['stemmer_id'] = $stemmer_id;
			$nummers[$nummer_id]['stemmen'][$stemmer_id]['ip'] = $ip;
			$nummers[$nummer_id]['stemmen'][$stemmer_id]['is_behandeld'] = $is_behandeld;
			$nummers[$nummer_id]['stemmen'][$stemmer_id]['toelichting'] = $toelichting;
			$nummers[$nummer_id]['stemmen'][$stemmer_id]['timestamp'] = $timestamp;
			// $nummers[$nummer_id]['stemmen'][$stemmer_id]['is_geanonimiseerd'] = $is_geanonimiseerd;
			$nummers[$nummer_id]['stemmen'][$stemmer_id]['velden'][$veld_id] = [
				'type' => $type,
				'waarde' => $waarde
			];
		}
		foreach ( $nummers as $nummer_id => $nummer ) {
			foreach ( $nummers[$nummer_id]['stemmen'] as $stemmer_id => $stemmer ) {
				$nummers[$nummer_id]['stemmen'][$stemmer_id]['velden'] = array_values($stemmer['velden']);
			}
			$nummers[$nummer_id]['stemmen'] = array_values($nummers[$nummer_id]['stemmen']);
		}
		return array_values($nummers);
	}

}
