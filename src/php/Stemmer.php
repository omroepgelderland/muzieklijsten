<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

class Stemmer {
	
	private int $id;
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
		$this->ip = $data['ip'];
		
		$this->tijdstip = $data['timestamp'];
		$this->db_props_set = true;
	}

	public static function maak(
		string $ip
	): static {
		try {
			$id = DB::insertMulti('stemmers', [
				'ip' => $ip
			]);
			return new static($id);
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
	 * Maakt een object uit een id aangeleverd door HTTP POST.
	 * @param \stdClass $request HTTP-request.
	 * @return Stemmer
	 * @throws Muzieklijsten_Exception
	 */
	public static function maak_uit_request( \stdClass $request ): Stemmer {
		try {
			$id = filter_var($request->stemmer, FILTER_VALIDATE_INT);
		} catch ( UndefinedPropertyException ) {
			throw new Muzieklijsten_Exception('Geen stemmer in invoer');
		}
		if ( $id === false ) {
			throw new Muzieklijsten_Exception(sprintf(
				'Ongeldig stemmer id: %s',
				filter_var($request->stemmer)
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
		// Velden
		$velden = [];
		foreach ( $lijst->get_velden() as $veld ) {
			try {
				$velden[] = "{$veld->get_label()}: {$veld->get_stemmer_waarde($this)}";
			} catch ( Muzieklijsten_Exception $e ) {}
		}
		$velden_str = implode("\n", $velden);

		$nummers_lijst = [];
		foreach ( $this->get_stemmen($lijst) as $stem ) {
			$nummers_lijst[] = "{$stem->nummer->get_titel()} - {$stem->nummer->get_artiest()}";
			$nummers_lijst[] = "\tToelichting: {$stem->get_toelichting()}";
			$nummers_lijst[] = '';
		}
		$nummers_str = implode("\n", $nummers_lijst);

		$tekst_bericht = <<<EOT
		Ontvangen van:

		{$velden_str}

		{$nummers_str}
		EOT;

		$onderwerp = "Er is gestemd - {$lijst->get_naam()}";

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
