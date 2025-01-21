<?php

declare(strict_types=1);

namespace gldstdlib;

use gldstdlib\exception\GLDException;
use gldstdlib\exception\TypeException;

use function gldstdlib\safe\json_decode;

/**
 * Iterator voor het maken van objecten uit ID’s uit de database.
 *
 * @template T of 'int'|'string'|'bool'|'float'|'json'
 *
 * @implements \Iterator<int, T>
 */
class DBIterator implements \Iterator
{
    /** @var ?(T is 'int' ? int : (T is 'string' ? string : (T is 'bool' ? bool : (T is 'float' ? float : object)))) */
    private mixed $current_value;
    private int $position;

    /**
     * @param T $type Type.
     * @param $statement Queryresultaat. Alleen de eerste kolom
     * wordt gebruikt.
     */
    public function __construct(
        private string $type,
        private \PDOStatement $statement,
    ) {
        $this->current_value = null;
        $this->position = -1;
        $this->next();
    }

    /**
     * @return (T is 'int' ? int : (T is 'string' ? string : (T is 'bool' ? bool : (T is 'float' ? float : object))))
     */
    public function current(): mixed
    {
        if ($this->current_value === null) {
            throw new GLDException();
        }
        return $this->current_value;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        /** @var array<int|string, string|int|float>|false */
        $fetched = $this->statement->fetch(\PDO::FETCH_NUM);
        if ($fetched === false) {
            $this->current_value = null;
        } else {
            $this->current_value = $this->filter($fetched[0]);
        }
        $this->position++;
    }

    /**
     * @throws GLDException
     */
    public function rewind(): void
    {
        if ($this->position !== 0) {
            throw new GLDException(
                'Kan iterator niet terugspoelen. Voer een nieuwe query uit.'
            );
        }
    }

    public function valid(): bool
    {
        return isset($this->current_value);
    }

    /**
     * @return (T is 'int' ? int : (T is 'string' ? string : (T is 'bool' ? bool : (T is 'float' ? float : object))))
     */
    private function filter(string|int|float $value)
    {
        switch ($this->type) {
            case 'int':
                return filter_int($value);
            case 'string':
                return (string)$value;
            case 'bool':
                return filter_bool($value);
            case 'float':
                return filter_float($value);
            case 'json':
                $json = json_decode((string)$value);
                if (!\is_object($json)) {
                    TypeException::throw_unexpected('object', \gettype($json));
                }
                return $json;
            default:
                TypeException::throw_invalid($this->type);
        }
    }
}
