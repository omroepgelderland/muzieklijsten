<?php

/**
 * Abstractielaag voor de database. Dit is een singleton class
 */
class Muzieklijsten_Database {
	
	private static $muzieklijsten_db;
	private $db;
	
	/**
	 * Maakt een nieuw object. Mag alleen vanuit deze class worden aangeroepen
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	protected function __construct() {
		$hostname = 'localhost';
		if ( is_dev() ) {
			$database = 'remy_muzieklijsten';
			$user = 'remy';
			$password = 'TxpJOe5MV0y4vP6t';
		} else {
			$database = 'rtvgelderland';
			$user = 'w3omrpg';
			$password = 'H@l*lOah';
		}
		$this->db = new MySQLi($hostname, $user, $password, $database);
		if ( $this->db->connect_error ) {
			throw new SQLException(
				sprintf(
					'Kan geen verbinding met de database maken (fout %s). Details: %s',
					$this->db->connect_errno,
					$this->db->connect_error
				),
				$this->db->connect_errno
			);
		}
		$this->db->set_charset('UTF-8');
		$this->db->query('SET NAMES utf8');
	}
	
	/**
	 * Singleton classes kunnen niet worden gekloond.
	 */
	private function __clone() {}
	
	/**
	 * Singleton classes kunnen niet worden geserialiseerd.
	 */
	private function __wakeup() {}
	
	/**
	 * Sluit de database weer af.
	 */
	public function __destruct() {
		$this->db->close();
	}
	
	/**
	 * Geeft het database object.
	 * @return MySQLi Het database object
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function getDB() {
		if ( self::$muzieklijsten_db === null ) {
			self::$muzieklijsten_db = new self();
		}
		return self::$muzieklijsten_db->db;
	}
	
	/**
	 * Zet MySQLi autocommit uit.
	 * @throws SQLException Als autocommit niet uitgezet kan worden.
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function disableAutocommit() {
		$db = self::getDB();
		if ( ! $db->autocommit(false) ) {
			self::throwException('Autocommit uitzetten mislukt');
		}
	}
	
	/**
	 * Zet speciale tekens om en voorkomt SQL injectie
	 * @param string $str Waarde die omgezet moet worden
	 * @return string Omgezette string
	 */
	public static function escape_string( $str ) {
		return self::getDB()->escape_string($str);
	}
	
	/**
	 * Voer een MySQLi commit uit.
	 * @throws SQLException Als de commit is mislukt.
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function commit() {
		if ( ! self::getDB()->commit() ) {
			self::throwException('Commit mislukt');
		}
	}
	
	/**
	 * Voert een MySQLi rollback uit.
	 * @throws SQLException Als de rollback is mislukt.
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function rollback() {
		if ( ! self::getDB()->rollback() ) {
			self::throwException('Rollback mislukt');
		}
	}
	
	/**
	 * Voert een MySQL query uit.
	 * @param string $sql SQL query
	 * @return mysqli_result Resultaat
	 * @throws SQLException Als de query mislukt.
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function query( $sql ) {
		$sql = (string)$sql;
		$db = self::getDB();
		
		$res = $db->query($sql);
		if ( $res === false ) {
			self::throwQueryException($sql);
		}
		return $res;
	}
	
	/**
	 * Geef de eerste kolom van de eerste rij van het resultaat van een SQL query terug. Er wordt een Exception gegeven als er geen resultaat is.
	 * @param string $sql SQL query
	 * @return string resultaat
	 * @throws SQLException Als er geen resultaat is.
	 * @throws SQLException Als de query mislukt.
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function selectSingle( $sql ) {
		$sql = (string)$sql;
		$db = self::getDB();
		
		$res = $db->query($sql);
		if ( $res === false ) {
			self::throwQueryException($sql);
		}
		if ( $res->num_rows === 0 ) {
			throw new SQLException(sprintf(
				'Geen resultaat bij query: "%s"',
				$sql
			));
		}
		return self::typecast($res->fetch_row()[0], $res->fetch_field()->type);
	}
	
	/**
	 * Geef de eerste rij van het resultaat van een SQL query terug. Er wordt een Exception gegeven als er geen resultaat is.
	 * @param string $sql SQL query
	 * @return array associatieve array met resultaat
	 * @throws Exception Als er geen resultaat is.
	 * @throws SQLException Als de query mislukt.
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function selectSingleRow( $sql ) {
		$sql = (string)$sql;
		$db = self::getDB();
		
		$res = $db->query($sql);
		if ( $res === false ) {
			self::throwQueryException($sql);
		}
		if ( $res->num_rows === 0 ) {
			throw new SQLException(sprintf(
				'Geen resultaat bij query: "%s"',
				$sql
			));
		}
		$ret = [];
		foreach ( $res->fetch_assoc() as $key => $value ) {
			$ret[$key] = self::typecast($value, $res->fetch_field()->type);
		}
		return $ret;
	}
	
	/**
	 * Geef de eerste kolom van het resultaat van een SQL query terug.
	 * @param string $sql SQL query
	 * @return array resultaat. Kan leeg zijn.
	 * @throws SQLException Als de query mislukt.
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function selectSingleColumn( $sql ) {
		$sql = (string)$sql;
		$db = self::getDB();
		
		$res = $db->query($sql);
		if ( $res === false ) {
			self::throwQueryException($sql);
		}
		$type = $res->fetch_field()->type;
		$ret = [];
		while ( ( $r = $res->fetch_row() ) !== null ) {
			array_push($ret, self::typecast($r[0], $type));
		}
		return $ret;
	}
	
	/**
	 * Geeft aan of een of meerdere databaserecords bestaan.
	 * @param string $sql Query
	 * @param int $min Minumum aantal resultaten. Standaard 1
	 * @param int $max Maximum aantal resultaten. Standaard 1
	 * @return boolean
	 * @throws SQLException bij databasefouten
	 */
	public static function recordBestaat( $sql, $min = 1, $max = 1 ) {
		$sql = (string)$sql;
		$db = self::getDB();
		
		$res = $db->query($sql);
		if ( $res === false ) {
			self::throwQueryException($sql);
		}
		return ( $res->num_rows >= $min && $res->num_rows <= $max );
	}
	
	/**
	 * Zet een aantal velden in de database aan de hand van een associatieve array. PHP types worden naar SQL omgezet. Strings worden ge-escaped
	 * @param string $table Naam van de tabel waarin de data moet worden geplaatst.
	 * @param array $data Associatieve array met kolomnamen als keys en in te voegen gegevens als values.
	 * @returns mixed Het ID van de ingevoegde rij.
	 * @throws Exception Als er niets is toegevoegd na uitvoering van de query.
	 * @throws SQLException Als de query mislukt.
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function insertMulti( $table, $data = [] ) {
		$table = (string)$table;
		$data = (array)$data;
		$db = self::getDB();
		
		$columnstr = '';
		$valuestr = '';
		foreach ( $data as $key => $value ) {
			if ( strlen($columnstr) > 0 ) {
				$columnstr .= ', ';
				$valuestr .= ', ';
			}
			$columnstr .= $key;
			if ( $value === null ) {
				$valuestr .= 'NULL';
			} elseif ( $value === 'NOW()' ) {
				$valuestr .= 'NOW()';
			} elseif ( $value === true ) {
				$valuestr .= '1';
			} elseif ( $value === false ) {
				$valuestr .= '0';
			} elseif ( is_numeric($value) ) {
				$valuestr .= sprintf('%s', $value);
			} elseif ( $value instanceof DateTime ) {
				$valuestr .= $value->format('"Y-m-d H:i:s"');
			} else {
				$valuestr .= sprintf('"%s"', $db->real_escape_string($value));
			}
		}
		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)',
			$table,
			$columnstr,
			$valuestr
		);
		$res = $db->query($sql);
		if ( $res === false ) {
			self::throwQueryException($sql);
		}
		if ( $db->affected_rows == 0 ) {
			throw new SQLException(sprintf(
				'Toevoegen van rij mislukt. Query: "%s"',
				$sql
			));
		}
		return $db->insert_id;
	}
	
	/**
	 * Verander een aantal velden in de database aan de hand van een associatieve array. PHP types worden naar SQL omgezet. Strings worden ge-escaped
	 * @param string $table Naam van de tabel waar de data moet worden aangepast.
	 * @param array $data Associatieve array met kolomnamen als keys en in te voegen gegevens als values.
	 * @param string $conditions Voorwaarden voor binnen het WHERE-statement
	 * @return int Aantal veranderde rijen
	 * @throws SQLException Als de query mislukt.
	 * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
	 */
	public static function updateMulti( $table, $data, $conditions ) {
		$table = (string)$table;
		$data = (array)$data;
		$conditions = (string)$conditions;
		$db = self::getDB();
		
		$datastr = '';
		foreach ( $data as $key => $value ) {
			if ( strlen($datastr) > 0 ) {
				$datastr .= ', ';
			}
			if ( $value === null ) {
				$datastr .= sprintf('%s=NULL', $key);
			} elseif ( $value === 'NOW()' ) {
				$datastr .= sprintf('%s=NOW()', $key);
			} elseif ( $value === true ) {
				$datastr .= sprintf('%s=1', $key);
			} elseif ( $value === false ) {
				$datastr .= sprintf('%s=0', $key);
			} elseif ( is_numeric($value) ) {
				$datastr .= sprintf('%s=%s', $key, $value);
			} elseif ( $value instanceof DateTime ) {
				$datastr .= sprintf('%s="%s"', $key, $value->format('Y-m-d H:i:s'));
			} else {
				$datastr .= sprintf('%s="%s"', $key, $db->real_escape_string($value));
			}
		}
		if ( $conditions == null ) {
			$sql = sprintf(
			'UPDATE %s SET %s',
				$table,
				$datastr
			);
		} else {
			$sql = sprintf(
			'UPDATE %s SET %s WHERE %s',
				$table,
				$datastr,
				$conditions
			);
		}
		$res = $db->query($sql);
		if ( $res === false ) {
			self::throwQueryException($sql);
		}
		return $db->affected_rows;
	}
	
	/**
	 * Zet een waarde uit de database om naar het juiste PHP type aan de hand van het MySQL veldtype
	 * @param string $waarde	Waarde uit de database
	 * @param int $type	MySQLi type
	 * @return mixed
	 */
	private static function typecast( $waarde, $type ) {
		if ( $waarde === null ) {
			return null;
		}
		if ( $type === MYSQLI_TYPE_DECIMAL ) {
			return self::typecast_nummer((float)$waarde);
		}
		if ( $type === MYSQLI_TYPE_NEWDECIMAL ) {
			return self::typecast_nummer((float)$waarde);
		}
//		if ( $type === MYSQLI_TYPE_BIT ) {
//			return $waarde;
//		}
		if ( $type === MYSQLI_TYPE_TINY ) {
			return (int)$waarde;
		}
		if ( $type === MYSQLI_TYPE_SHORT ) {
			return (int)$waarde;
		}
		if ( $type === MYSQLI_TYPE_LONG ) {
			return self::typecast_nummer((float)$waarde);
		}
		if ( $type === MYSQLI_TYPE_FLOAT ) {
			return self::typecast_nummer((float)$waarde);
		}
		if ( $type === MYSQLI_TYPE_DOUBLE ) {
			return self::typecast_nummer((float)$waarde);
		}
		if ( $type === MYSQLI_TYPE_NULL ) {
			return null;
		}
		if ( $type === MYSQLI_TYPE_TIMESTAMP ) {
			return (int)$waarde;
		}
		if ( $type === MYSQLI_TYPE_LONGLONG ) {
			return self::typecast_nummer((float)$waarde);
		}
		if ( $type === MYSQLI_TYPE_INT24 ) {
			return self::typecast_nummer((float)$waarde);
		}
		if ( $type === MYSQLI_TYPE_DATE ) {
			return new DateTime($waarde);
		}
//		if ( $type === MYSQLI_TYPE_TIME ) {
//			return $waarde;
//		}
		if ( $type === MYSQLI_TYPE_DATETIME ) {
			return new DateTime($waarde);
		}
		if ( $type === MYSQLI_TYPE_YEAR ) {
			return (int)$waarde;
		}
//		if ( $type === MYSQLI_TYPE_NEWDATE ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_INTERVAL ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_ENUM ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_SET ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_TINY_BLOB ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_MEDIUM_BLOB ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_LONG_BLOB ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_BLOB ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_VAR_STRING ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_STRING ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_CHAR ) {
//			return $waarde;
//		}
//		if ( $type === MYSQLI_TYPE_GEOMETRY ) {
//			return $waarde;
//		}
		return $waarde;
	}
	
	/**
	 * Zet floats om in ints als dat kan zonder dataverlies
	 * @param int|float $waarde
	 * @return int|float
	 */
	private static function typecast_nummer( $waarde ) {
		if ( $waarde == (int)$waarde ) {
			return (int)$waarde;
		} else {
			return $waarde;
		}
	}
	
	/**
	 * Genereert een foutmelding aan de hand van de foutinformatie uit MySQLi
	 * @param string $sql	De query die de fout veroorzaakte
	 * @throws SQLException
	 */
	private static function throwQueryException( $sql ) {
		self::throwException(sprintf(
			'Query: "%s"',
			$sql
		));
	}
	
	/**
	 * Genereert een foutmelding aan de hand van de foutinformatie uit MySQLi
	 * @param string $msg	Foutinformatie
	 * @throws SQLException
	 */
	private static function throwException( $msg ) {
		$db = self::getDB();
		$e_msg = sprintf(
			'%s. Error: "%s". Errornummer: %d.',
			$msg,
			$db->error,
			$db->errno
		);
		switch ( $db->errno ) {
			case 1062:
				throw new SQLException_DupEntry($e_msg, $db->errno);
			case 1406:
				throw new SQLException_DataTooLong($e_msg, $db->errno);
			default:
				throw new SQLException($e_msg, $db->errno);
		}
	}
	
}
