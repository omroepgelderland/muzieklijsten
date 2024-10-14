<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

use mysqli;
use mysqli_result;

/**
 * Abstractielaag voor de database. Dit is een singleton class
 */
class DB {
    
    private static DB $muzieklijsten_db;
    private mysqli $db;
    
    /**
     * Maakt een nieuw object. Mag alleen vanuit deze class worden aangeroepen
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    protected function __construct() {
        $this->db = new \mysqli(
            Config::get_instelling('sql', 'server'),
            Config::get_instelling('sql', 'user'),
            Config::get_instelling('sql', 'password'),
            Config::get_instelling('sql', 'database')
        );
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
    }
    
    /**
     * Singleton classes kunnen niet worden gekloond.
     */
    private function __clone() {}
    
    /**
     * Sluit de database weer af.
     */
    public function __destruct() {
        $this->db->close();
    }
    
    /**
     * Geeft het database object.
     * @return mysqli Het database object
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function getDB(): mysqli {
        self::$muzieklijsten_db ??= new self();
        return self::$muzieklijsten_db->db;
    }
    
    /**
     * Zet MySQLi autocommit uit.
     * @throws SQLException Als autocommit niet uitgezet kan worden.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function disableAutocommit(): void {
        $db = self::getDB();
        if ( ! $db->autocommit(false) ) {
            self::throwException('Autocommit uitzetten mislukt');
        }
    }
    
    /**
     * Zet speciale tekens om en voorkomt SQL injectie
     * @param $str Waarde die omgezet moet worden
     * @return string Omgezette string
     */
    public static function escape_string( string $str ): string {
        return self::getDB()->escape_string($str);
    }
    
    /**
     * Voer een MySQLi commit uit.
     * @throws SQLException Als de commit is mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function commit(): void {
        if ( ! self::getDB()->commit() ) {
            self::throwException('Commit mislukt');
        }
    }
    
    /**
     * Voert een MySQLi rollback uit.
     * @throws SQLException Als de rollback is mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function rollback(): void {
        if ( ! self::getDB()->rollback() ) {
            self::throwException('Rollback mislukt');
        }
    }
    
    /**
     * Voert een MySQL query uit.
     * @param $sql SQL query
     * @return ?\mysqli_result Resultaat
     * @throws SQLException Als de query mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function query( string $sql ): ?\mysqli_result {
        try {
            $res = self::getDB()->query($sql);
            if ( $res === false ) {
                self::throwQueryException($sql);
            }
        } catch ( \mysqli_sql_exception $e ) {
            self::throwQueryException($sql, $e);
        }
        if ( $res instanceof mysqli_result ) {
            return $res;
        } else {
            return null;
        }
    }
    
    /**
     * Geef de eerste kolom van de eerste rij van het resultaat van een SQL query terug. Er wordt een Exception gegeven als er geen resultaat is.
     * @param $sql SQL query
     * @return DBWaarde
     * @throws SQLException Als er geen resultaat is.
     * @throws SQLException Als de query mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function selectSingle( string $sql ): mixed {
        $res = self::query($sql);
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
     * @param $sql SQL query
     * @return array<string, DBWaarde> associatieve array met resultaat
     * @throws Exception Als er geen resultaat is.
     * @throws SQLException Als de query mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function selectSingleRow( string $sql ): array {
        $res = self::query($sql);
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
     * @param $sql SQL query
     * @return list<DBWaarde> resultaat. Kan leeg zijn.
     * @throws SQLException Als de query mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function selectSingleColumn( string $sql ): array {
        $res = self::query($sql);
        $type = $res->fetch_field()->type;
        $ret = [];
        while ( ( $r = $res->fetch_row() ) !== null ) {
            $ret[] = self::typecast($r[0], $type);
        }
        return $ret;
    }
    
    /**
     * Geeft een lijst met objecten aan de hand van een query.
     * De query moet als resultaat een enkele rij met id's als resultaat geven
     * die overeenkomen met ID's van het gewenste objecttype.
     * @template Type
     * @param $sql De query
     * @param class-string<Type> $object_type Naam van de class
     * @param mixed $args,... Extra parameters voor de constructor van de class
     * (na id)
     * @return list<Type> resultaat. Kan leeg zijn.
     * @throws SQLException Als de query mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de
     * database.
     */
    public static function selectObjectLijst( string $sql, $object_type, ...$args ): array {
        $respons = [];
        foreach ( self::selectSingleColumn($sql) as $id ) {
            $respons[] = new $object_type($id, ...$args);
        }
        return $respons;
    }
    
    /**
     * Geeft aan of een of meerdere databaserecords bestaan.
     * @param $sql Query
     * @param $min Minimum aantal resultaten. Standaard 1
     * @param $max Maximum aantal resultaten. Standaard 1
     * @return bool
     * @throws SQLException bij databasefouten
     */
    public static function recordBestaat( string $sql, int $min = 1, int $max = 1 ): bool {
        $res = self::query($sql);
        return ( $res->num_rows >= $min && $res->num_rows <= $max );
    }
    
    /**
     * Zet een aantal velden in de database aan de hand van een associatieve array. PHP types worden naar SQL omgezet. Strings worden ge-escaped
     * @param $table Naam van de tabel waarin de data moet worden geplaatst.
     * @param array<string, DBWaarde> $data Associatieve array met kolomnamen als keys en in te
     * voegen gegevens als values.
     * @throws Exception Als er niets is toegevoegd na uitvoering van de query.
     * @throws SQLException Als de query mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function insertMulti( string $table, array $data ) {
        $db = self::getDB();
        $data = static::prepareer_data($data);
        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(',', array_keys($data)),
            implode(',', array_values($data))
        );
        static::query($query);
        if ( $db->affected_rows == 0 ) {
            throw new SQLException(sprintf(
                'Toevoegen van rij mislukt. Query: "%s"',
                $query
            ));
        }
        return $db->insert_id;
    }
    
    /**
     * Verander een aantal velden in de database aan de hand van een associatieve array. PHP types worden naar SQL omgezet. Strings worden ge-escaped
     * @param $table Naam van de tabel waar de data moet worden aangepast.
     * @param array<string, DBWaarde> $data Associatieve array met kolomnamen als keys en in te
     * voegen gegevens als values.
     * @param $conditions Voorwaarden voor binnen het WHERE-statement
     * @return int Aantal veranderde rijen
     * @throws SQLException Als de query mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function updateMulti( string $table, array $data, string $conditions ): int {
        $db = self::getDB();
        $data = static::prepareer_data($data);
        $updates = [];
        foreach ( $data as $key => $value ) {
            $updates[] = "{$key}={$value}";
        }
        $updates_str = implode(',', $updates);
        DB::query("UPDATE {$table} SET {$updates_str} WHERE {$conditions}");
        return $db->affected_rows;
    }
    
    /**
     * Zet een waarde uit de database om naar het juiste PHP type aan de hand van het MySQL veldtype
     * @param mixed $waarde Waarde uit de database
     * @param $type MySQLi type
     * @return DBWaarde
     */
    private static function typecast( mixed $waarde, int $type ): mixed {
        if ( $waarde === null ) {
            return null;
        }
        if ( $type === MYSQLI_TYPE_DECIMAL ) {
            return self::typecast_nummer((float)$waarde);
        }
        if ( $type === MYSQLI_TYPE_NEWDECIMAL ) {
            return self::typecast_nummer((float)$waarde);
        }
//    if ( $type === MYSQLI_TYPE_BIT ) {
//        return $waarde;
//    }
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
            return new \DateTime($waarde);
        }
        if ( $type === MYSQLI_TYPE_LONGLONG ) {
            return self::typecast_nummer((float)$waarde);
        }
        if ( $type === MYSQLI_TYPE_INT24 ) {
            return self::typecast_nummer((float)$waarde);
        }
        if ( $type === MYSQLI_TYPE_DATE ) {
            return new \DateTime($waarde);
        }
//        if ( $type === MYSQLI_TYPE_TIME ) {
//            return $waarde;
//        }
        if ( $type === MYSQLI_TYPE_DATETIME ) {
            return new \DateTime($waarde);
        }
        if ( $type === MYSQLI_TYPE_YEAR ) {
            return (int)$waarde;
        }
//        if ( $type === MYSQLI_TYPE_NEWDATE ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_INTERVAL ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_ENUM ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_SET ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_TINY_BLOB ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_MEDIUM_BLOB ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_LONG_BLOB ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_BLOB ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_VAR_STRING ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_STRING ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_CHAR ) {
//            return $waarde;
//        }
//        if ( $type === MYSQLI_TYPE_GEOMETRY ) {
//            return $waarde;
//        }
        return $waarde;
    }
    
    /**
     * Zet floats om in ints als dat kan zonder dataverlies
     * @param $waarde
     */
    private static function typecast_nummer( int|float $waarde ): int|float {
        if ( $waarde == (int)$waarde ) {
            return (int)$waarde;
        } else {
            return $waarde;
        }
    }
    
    /**
     * Genereert een foutmelding aan de hand van de foutinformatie uit MySQLi.
     * @param $sql De query die de fout veroorzaakte.
     * @param $e Door PHP gegooide error (optioneel).
     * @throws SQLException
     */
    private static function throwQueryException( string $sql, ?\mysqli_sql_exception $e = null ): never {
        self::throwException(sprintf(
            'Query: "%s"',
            $sql
        ), $e);
    }
    
    /**
     * Genereert een foutmelding aan de hand van de foutinformatie uit MySQLi.
     * @param $msg Foutinformatie
     * @param $e Door PHP gegooide error (optioneel).
     * @throws SQLException
     */
    private static function throwException( string $msg, ?\mysqli_sql_exception $e = null ): never {
        $db = self::getDB();
        if ( isset($e) ) {
            $error = $e->getMessage();
            $errno = $e->getCode();
        } else {
            $error = $db->error;
            $errno = $db->errno;
        }
        $e_msg = sprintf(
            '%s. Error: "%s". Errornummer: %d.',
            $msg,
            $error,
            $errno
        );
        switch ( $errno ) {
            case 1062:
                throw new SQLException_DupEntry($e_msg, $db->errno);
            case 1406:
                throw new SQLException_DataTooLong($e_msg, $db->errno);
            default:
                throw new SQLException($e_msg, $db->errno);
        }
    }
    
    /**
     * Geeft de maximale mogelijke lengte van de invoer in een kolom.
     * @param $tabel Tabel.
     * @param $kolom Kolom.
     */
    public static function get_max_kolom_lengte( string $tabel, string $kolom ): int {
        return self::selectSingle("SELECT max(length(`{$kolom}`)) `max_column_length` from `{$tabel}`");
    }
    
    /**
     * Voegt een entry toe of update een bestaande entry als de primary keys of unieke velden van de invoer al bestaan in de database.
     * PHP types worden naar SQL omgezet. Strings worden ge-escaped
     * @param $table Naam van de tabel waarin de data moet worden geplaatst.
     * @param array<string, DBWaarde> $data Associatieve array met kolomnamen als keys en in te
     * voegen gegevens als values.
     * @return DBInsertUpdateResult Object met informatie over de uitgevoerde actie en het
     * resultaat.
     * @throws SQLException Als de query mislukt.
     * @throws SQLException Als er geen verbinding kan worden gemaakt met de database.
     */
    public static function insert_update_multi( string $table, array $data = [] ): object {
        $db = static::getDB();
        $data = static::prepareer_data($data);
        $update_values = [];
        foreach ( $data as $key => $value ) {
            $update_values[] = sprintf('%s=%s', $key, $data[$key]);
        }
        static::query(sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(',', array_keys($data)),
            implode(',', array_values($data)),
            implode(',', $update_values)
        ));
        $respons = [
            'actie' => $db->affected_rows === 1 ? 'insert' : 'update',
            'veranderd' => $db->affected_rows > 0
        ];
        if ( $db->affected_rows > 0 ) {
            $respons['id'] = $db->insert_id;
        }
        return (object)$respons;
    }
    
    /**
     * Prepareert een associatieve array met keys en values voor gebruik in een SQL-query
     * @param array<string, DBWaarde> $data Invoer
     * @return array uitvoer
     */
    protected static function prepareer_data( array $data ): array {
        $respons = [];
        foreach ( $data as $key => $value ) {
            $key = sprintf('`%s`', $key);
            if ( $value === null ) {
                $respons[$key] = 'NULL';
            } elseif ( $value === 'NOW()' ) {
                $respons[$key] = 'NOW()';
            } elseif ( $value === true ) {
                $respons[$key] = '1';
            } elseif ( $value === false ) {
                $respons[$key] = '0';
            } elseif ( is_int($value) || is_float($value) ) {
                $respons[$key] = sprintf('%s', $value);
            } elseif ( $value instanceof \DateTime ) {
                $respons[$key] = $value->format('"Y-m-d H:i:s"');
            } else {
                $respons[$key] = sprintf('"%s"', static::escape_string($value));
            }
        }
        return $respons;
    }
    
}
