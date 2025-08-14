<?php

declare(strict_types=1);

namespace gldstdlib;

use gldstdlib\exception\GLDException;

/**
 * Iterator voor het maken van objecten uit ID’s uit de database.
 *
 * @template T of object
 *
 * @implements \Iterator<int, T>
 */
class DBObjectIterator implements \Iterator
{
    /** @var ?T */
    private ?object $current_object;
    private int $position;
    /** @var array<mixed> */
    private array $args;

    /**
     * @param class-string<T> $type Class.
     * @param $statement Queryresultaat. Alleen de eerste kolom
     * wordt gebruikt.
     * @param ...$args Optionele extra parameters voor de constructor van het
     * objecttype.
     */
    public function __construct(
        private string $type,
        private \PDOStatement $statement,
        mixed ...$args,
    ) {
        $this->args = $args;
        $this->current_object = null;
        $this->position = -1;
        $this->next();
    }

    /**
     * @return T
     */
    public function current(): object
    {
        if ($this->current_object === null) {
            throw new GLDException();
        }
        return $this->current_object;
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
            $this->current_object = null;
        } else {
            $this->current_object = new $this->type($fetched[0], ...$this->args);
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
        return isset($this->current_object);
    }
}
