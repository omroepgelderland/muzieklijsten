<?php

declare(strict_types=1);

namespace gldstdlib;

use gldstdlib\exception\NullException;
use gldstdlib\exception\SQLDataTooLongException;
use gldstdlib\exception\SQLDupEntryException;
use gldstdlib\exception\SQLException;
use gldstdlib\exception\SQLLockDeadlockException;
use gldstdlib\exception\SQLNoDefaultForFieldException;
use gldstdlib\exception\SQLNoResultException;
use gldstdlib\exception\SQLServerGoneException;
use Medoo\Medoo;

/**
 * Abstractielaag voor de database.
 *
 * @phpstan-type ParamsType array{
 *     host: string,
 *     database: string,
 *     username: string,
 *     password: string,
 *     collation: string
 * }
 * @phpstan-type DBInsertUpdateResultType object{
 *     actie: "insert"|"update",
 *     veranderd: bool,
 *     id?: int|string
 * }
 */
class DB
{
    public const ER_DUP_ENTRY = 1062;
    public const ER_LOCK_DEADLOCK = 1213;
    public const ER_NO_DEFAULT_FOR_FIELD = 1364;
    public const ER_DATA_TOO_LONG = 1406;
    public const CR_SERVER_GONE_ERROR = 2006;
    public const CR_SERVER_LOST = 2013;
    public const ER_USER_LOCK_DEADLOCK = 3058;

    /** @var ParamsType */
    private array $params;
    private ?Medoo $db;

    /**
     * @param ParamsType $params
     */
    public function __construct(
        array $params,
    ) {
        $this->params = $params;
    }

    /**
     * Geeft het Medoo database-object.
     *
     * @throws \PDOException
     */
    public function get_db(): Medoo
    {
        return $this->db ??= new Medoo([
            'type' => 'mariadb',
            'error' => \PDO::ERRMODE_EXCEPTION,
            ...$this->params,
        ]);
    }

    /**
     * Geeft een Medoo-object in testmode. Deze genereert SQL-queries maar voert
     * deze niet uit en maakt geen verbinding met de database.
     */
    protected function get_dummy_db(): Medoo
    {
        return new Medoo([
            'type' => 'mariadb',
            'error' => \PDO::ERRMODE_EXCEPTION,
            'testMode' => true,
            ...$this->params,
        ]);
    }

    /**
     * Geeft het onderliggende PDO-object.
     */
    public function get_pdo(): \PDO
    {
        return $this->get_db()->pdo;
    }

    /**
     * Zet MySQLi autocommit uit.
     *
     * @throws \PDOException Als autocommit niet uitgezet kan worden.
     */
    public function begin_transaction(): void
    {
        if (!$this->get_pdo()->beginTransaction()) {
            $this->throw_exception_db();
        }
    }

    /**
     * Voer een commit uit.
     *
     * @throws \PDOException Als de commit is mislukt.
     */
    public function commit(): void
    {
        if (!$this->get_pdo()->commit()) {
            $this->throw_exception_db();
        }
    }

    /**
     * Geeft aan of er een open transaction is. Als autocommit aan staat is dit
     * niet zo.
     */
    public function in_transaction(): bool
    {
        return $this->get_pdo()->inTransaction();
    }

    /**
     * Voert een MySQLi rollback uit.
     *
     * @throws \PDOException Als de rollback is mislukt.
     */
    public function rollback(): void
    {
        if (!$this->get_pdo()->rollBack()) {
            $this->throw_exception_db();
        }
    }

    /**
     * Zet speciale tekens om en voorkomt SQL injectie
     *
     * @param $str Waarde die omgezet moet worden
     *
     * @return string Omgezette string
     */
    public function escape_string(string $str): string
    {
        return $this->get_db()->quote($str);
    }

    /**
     * Voert een raw MySQL statement uit.
     *
     * @param $statement The raw SQL statement.
     * @param array<string, mixed> $map The array of input parameters value for
     * prepared statement.
     *
     * @throws \PDOException Als de query mislukt.
     */
    public function query(string $statement, array $map = []): \PDOStatement
    {
        $res = $this->get_db()->query($statement, $map);
        if ($res === null) {
            $this->throw_exception_db();
        }
        return $res;
    }

    /**
     * Geeft een querystring voor een SELECT-query.
     *
     * @param $table
     * @param string|list<string> $columns
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     *
     * @return string Query.
     */
    private function get_select_query(
        string $table,
        string|array $columns,
        array $where = [],
        array $join = [],
    ): string {
        $dummy_db = $this->get_dummy_db();
        if (count($where) + count($join) === 0) {
            // @phpstan-ignore arguments.count, argument.type
            $dummy_db->select($table, $columns);
        } elseif (count($join) === 0) {
            // @phpstan-ignore arguments.count, argument.type
            $dummy_db->select($table, $columns, $where);
        } else {
            // @phpstan-ignore arguments.count, argument.type
            $dummy_db->select($table, $join, $columns, $where);
        }
        return $dummy_db->queryString;
    }

    /**
     * Voert een selectquery uit.
     *
     * @param $table
     * @param string|list<string> $columns
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     *
     * @return array<array<mixed>>
     */
    private function execute_select_query(
        string $table,
        string|array $columns,
        array $where = [],
        array $join = [],
    ): array {
        if (count($where) + count($join) === 0) {
            // @phpstan-ignore arguments.count, argument.type
            $res = $this->get_db()->select($table, $columns);
        } elseif (count($join) === 0) {
            // @phpstan-ignore arguments.count, argument.type
            $res = $this->get_db()->select($table, $columns, $where);
        } else {
            // @phpstan-ignore arguments.count, argument.type
            $res = $this->get_db()->select($table, $join, $columns, $where);
        }
        /** @var ?array<array<mixed>> $res */
        if ($res === null) {
            $this->throw_exception_db();
        }
        return $res;
    }

    /**
     * Geef de eerste kolom van de eerste rij van het resultaat van een SQL-
     * query terug. Er wordt een SQLGeenResultaat gegeven als er geen resultaat
     * is.
     *
     * @param $table
     * @param $column
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     * @param $nullable Of het resultaat null kan zijn.
     *
     * @throws SQLNoResultException Als er geen resultaat is.
     * @throws SQLException Als de query mislukt.
     * @throws \PDOException Als er geen verbinding kan worden gemaakt met de
     * database.
     * @throws NullException Als het resultaat null is en $nullable is false.
     * return ($nullable is true ? ?mixed : mixed)
     */
    public function select_single(
        string $table,
        string $column,
        array $where,
        array $join = [],
        bool $nullable = true,
    ): mixed {
        if (count($join) === 0) {
            $res = $this->get_db()->get($table, $column, $where);
        } else {
            /** @phpstan-ignore argument.type, arguments.count */
            $res = $this->get_db()->get($table, $join, $column, $where);
        }
        if ($res === null && !$this->has($table, $where, $join)) {
            throw new SQLNoResultException(\sprintf(
                'Geen resultaat bij query: "%s"',
                $this->get_db()->last()
            ));
        }
        if ($res === null && !$nullable) {
            $last = $this->get_db()->last();
            throw new NullException(
                "respons null bij query \"{$last}\""
            );
        }
        return $res;
    }

    /**
     * Geeft een iterator voor één kolom van het resultaat van een SQL query terug.
     *
     * @template T of 'int'|'string'|'bool'|'float'|'json'
     *
     * @param T $type Type van het resultaat.
     * @param $table
     * @param $column
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     *
     * @return DBIterator<T> Resultaat.
     */
    public function select_column(
        string $type,
        string $table,
        string $column,
        array $where = [],
        array $join = [],
    ): DBIterator {
        $querystring = $this->get_select_query($table, $column, $where, $join);

        $statement = $this->get_db()->query($querystring);

        if ($statement === null) {
            $this->throw_exception_db();
        }
        return new DBIterator($type, $statement);
    }

    /**
     * Geeft een entry van één enkele rij.
     *
     * @param $table
     * @param list<string>|string $columns
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     *
     * @return array<mixed> Lijst.
     *
     * @throws SQLNoResultException Als er geen resultaat is.
     */
    public function select_row(
        string $table,
        array|string $columns,
        array $where = [],
        array $join = [],
    ): array {
        $res = $this->execute_select_query($table, $columns, $where, $join);
        if (\count($res) === 0) {
            throw new SQLNoResultException();
        }
        return $res[0];
    }

    /**
     * Maakt een select-query en geeft het PDOStatement terug zodat het
     * resultaat niet in het geheel in het geheugen wordt geladen.
     *
     * @param $table
     * @param list<string> $columns
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     */
    public function select_pdo(
        string $table,
        array $columns,
        array $where = [],
        array $join = [],
    ): \PDOStatement {
        $querystring = $this->get_select_query($table, $columns, $where, $join);

        $statement = $this->get_db()->query($querystring);

        if ($statement === null) {
            $this->throw_exception_db();
        }
        return $statement;
    }

    /**
     * Geeft het gehele resultaat van een query.
     *
     * @param $table
     * @param list<string>|string $columns
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     *
     * @return array<mixed>
     */
    public function select(
        string $table,
        array|string $columns,
        array $where = [],
        array $join = [],
    ): array {
        return $this->execute_select_query($table, $columns, $where, $join);
    }

    /**
     * Geeft een lijst met objecten aan de hand van een query.
     * De query moet als resultaat een enkele rij met id's als resultaat geven
     * die overeenkomen met ID's van het gewenste objecttype.
     *
     * @template T of object
     *
     * @param class-string<T> $object_type Naam van de class.
     * @param $table
     * @param $column
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     * @param ...$args Eventuele extra parameters (na de eerste) voor de constructor van
     * de class.
     *
     * @return DBObjectIterator<T> Lijst.
     *
     * @throws SQLNoResultException Als er geen resultaat is.
     * @throws SQLException Als de query mislukt.
     * @throws \PDOException Als er geen verbinding kan worden gemaakt met de
     * database.
     * @throws NullException Als het resultaat null is en $nullable is false.
     */
    public function select_objecten(
        $object_type,
        string $table,
        string $column,
        array $where = [],
        array $join = [],
        mixed ...$args,
    ): DBObjectIterator {
        $querystring = $this->get_select_query($table, $column, $where, $join);

        $statement = $this->get_db()->query($querystring);

        if ($statement === null) {
            $this->throw_exception_db();
        }
        return new DBObjectIterator($object_type, $statement, ...$args);
    }

    /**
     * Geeft een object aan de hand van een query.
     * De query moet als resultaat een enkel resultaat met een waarde geven die
     * gebruikt wordt al de eerste parameter van de constructor van de opgegeven
     * class.
     *
     * @template T
     *
     * @param class-string<T> $object_type Naam van de class.
     * @param $table
     * @param $column
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     * @param ...$args Eventuele extra parameters (na de eerste) voor de constructor van
     * de class.
     *
     * @return T Resultaat object.
     *
     * @throws SQLNoResultException Als er geen resultaat is.
     * @throws SQLException Als de query mislukt.
     * @throws \PDOException Als er geen verbinding kan worden gemaakt met de
     * database.
     * @throws NullException Als het resultaat null is en $nullable is false.
     */
    public function select_object(
        $object_type,
        string $table,
        string $column,
        array $where = [],
        array $join = [],
        mixed ...$args,
    ) {
        $id = $this->select_single($table, $column, $where, $join, false);
        return new $object_type($id, ...$args);
    }

    /**
     * Geeft aan of een of meerdere databaserecords bestaan.
     *
     * @param $table
     * @param array<string, mixed> $where
     * @param array<string, mixed> $join
     */
    public function has(string $table, array $where, array $join = []): bool
    {
        if (count($join) > 0) {
            // @phpstan-ignore arguments.count
            return $this->get_db()->has($table, $join, $where);
        } else {
            return $this->get_db()->has($table, $where);
        }
    }

    /**
     * Zet een aantal velden in de database aan de hand van een associatieve
     * array. PHP types worden naar SQL omgezet. Strings worden ge-escaped.
     * Zie https://medoo.in/api/insert
     *
     * @param $table Naam van de tabel waarin de data moet worden geplaatst.
     * @param array<string, mixed> $values Associatieve array met in te voeren
     * data.
     *
     * @return int|string Het ID van de laatst ingevoegde rij.
     *
     * @throws SQLException Als er niets is toegevoegd na uitvoering van de
     * query.
     */
    public function insert(string $table, array $values): int|string
    {
        try {
            $statement = $this->get_db()->insert($table, $values);
        } catch (\PDOException $e) {
            $this->verwerk_pdo_exception($e);
        }
        if ($statement === null) {
            $this->throw_exception_db();
        }

        if ($statement->rowCount() === 0) {
            throw new SQLException(\sprintf(
                'Toevoegen van rij mislukt. Query: "%s"',
                $this->get_db()->last()
            ));
        }
        $id = $this->get_db()->id();
        if ($id === null) {
            throw new SQLException();
        }
        return $id;
    }

    /**
     * Voegt een entry toe of update een bestaande entry als de primary keys of
     * unieke velden van de invoer al bestaan in de database.
     * PHP types worden naar SQL omgezet. Strings worden ge-escaped.
     *
     * @param $table Naam van de tabel waarin de data moet worden geplaatst.
     * @param array<string, mixed> $values Associatieve array met in te voeren
     * data.
     *
     * @return int|string Het ID van de laatst ingevoegde rij.
     * @return DBInsertUpdateResultType Object met informatie over de uitgevoerde actie en het
     * resultaat.
     */
    public function insert_update(string $table, array $values): object
    {
        $dummy_db = $this->get_dummy_db();
        $dummy_db->insert($table, $values);
        $insert_str = $dummy_db->queryString;
        $dummy_db->update($table, $values);
        $update_str = $dummy_db->queryString;
        $update_str = \explode(' SET ', $update_str, 2)[1];
        $query = "{$insert_str} ON DUPLICATE KEY UPDATE {$update_str};";

        $statement = $this->get_db()->query($query);

        if ($statement === null) {
            $this->throw_exception_db();
        }

        $aantal = $statement->rowCount();
        $respons = [
            'actie' => $aantal === 1 ? 'insert' : 'update',
            'veranderd' => $aantal > 0,
        ];
        $id = $this->get_db()->id();
        if ($aantal > 0 && isset($id)) {
            $respons['id'] = $id;
        }
        return (object)$respons;
    }

    /**
     * Verander een aantal velden in de database aan de hand van een
     * associatieve array. PHP types worden naar SQL omgezet. Strings worden ge-
     * escaped
     *
     * @param $table Naam van de tabel waar de data moet worden
     * aangepast.
     * @param array<string, mixed> $data Associatieve array met bij te werken
     * data.
     * @param array<string, mixed> $where Voorwaarden voor binnen het
     * WHERE-statement.
     *
     * @return int Aantal veranderde rijen
     */
    public function update(
        string $table,
        array $data,
        array $where,
    ): int {
        $statement = $this->get_db()->update($table, $data, $where);
        if ($statement === null) {
            $this->throw_exception_db();
        }
        return $statement->rowCount();
    }

    /**
     * Genereert een foutmelding aan de hand van de foutinformatie uit PDO.
     *
     * @param $msg Foutinformatie.
     *
     * @throws SQLException
     */
    private function throw_exception_db(?string $msg = null): never
    {
        [$sqlstate_code, $mysql_code, $info_msg] = $this->get_pdo()->errorInfo();
        $error = $this->get_db()->error ?? $info_msg;

        $msg_delen = [];
        if ($msg !== null) {
            $msg_delen[] = $msg;
        }
        if ($error !== null && $error !== '') {
            $msg_delen[] = \sprintf('MySQL error: "%s"', $error);
        }
        if ($mysql_code !== null && $mysql_code > 0) {
            $msg_delen[] = \sprintf('Errornummer %d', $mysql_code);
        }
        if ($this->get_db()->last() !== null) {
            $msg_delen[] = \sprintf('Query: "%s"', $this->get_db()->last());
        }
        $e_msg = \implode('; ', $msg_delen);
        $this->throw_exception($e_msg, $mysql_code);
    }

    /**
     * Zet een PDO foutmelding om in een specifieke SQLException.
     */
    private function verwerk_pdo_exception(\PDOException $e): never
    {
        if ($e->errorInfo === null) {
            $this->throw_exception();
        }
        [$sqlstate_code, $mysql_code, $msg] = $e->errorInfo;
        $this->throw_exception($msg, $mysql_code, $e);
    }

    /**
     * Genereert een specifieke foutmelding aan de hand van een tekst en code.
     *
     * @param $msg
     * @param $errno
     *
     * @throws SQLException
     */
    private function throw_exception(
        string $msg = '',
        int $errno = 0,
        \Throwable $previous = null,
    ): never {
        switch ($errno) {
            case self::ER_DUP_ENTRY:
                throw new SQLDupEntryException($msg, $errno, $previous);
            case self::ER_LOCK_DEADLOCK:
                throw new SQLLockDeadlockException($msg, $errno, $previous);
            case self::ER_NO_DEFAULT_FOR_FIELD:
                throw new SQLNoDefaultForFieldException($msg, $errno, $previous);
            case self::ER_DATA_TOO_LONG:
                throw new SQLDataTooLongException($msg, $errno, $previous);
            case self::CR_SERVER_GONE_ERROR:
                $this->sluiten();
                throw new SQLServerGoneException($msg, $errno, $previous);
            case self::CR_SERVER_LOST:
                $this->sluiten();
                throw new SQLServerGoneException($msg, $errno, $previous);
            case self::ER_USER_LOCK_DEADLOCK:
                throw new SQLLockDeadlockException($msg, $errno, $previous);
            default:
                throw new SQLException($msg, $errno, $previous);
        }
    }

    /**
     * Sluit de database als die open is.
     * Nuttig voor continue processen om niet permanent de database te claimen.
     */
    public function sluiten(): void
    {
        $this->db = null;
    }
}
