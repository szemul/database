<?php
declare(strict_types=1);

namespace Szemul\Database\Result;

use PDOStatement;
use PDO;
use Iterator;

/** @implements \Iterator<int,array> */
class QueryResult implements Iterator
{
    /** @var array<string,string>|null */
    protected ?array $row      = null;
    protected ?int   $rowIndex = -1;

    public function __construct(protected PDOStatement $statement)
    {
    }

    /** @return array<string,string>|null */
    public function current(): ?array
    {
        if (is_null($this->row)) {
            return $this->next();
        }

        return $this->row;
    }

    public function key(): ?int
    {
        if (-1 === $this->rowIndex) {
            $this->next();
        }

        return $this->rowIndex;
    }

    /**
     * Returns the next row from the result and increments the row counter
     *
     * @return array<string,string>|null
     */
    public function next(): ?array
    {
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);
        if (false !== $row) {
            $this->row = $row;
            $this->rowIndex++;
        } else {
            $this->row      = null;
            $this->rowIndex = null;
        }

        return $this->row;
    }

    /**
     * Returns TRUE if the current row is in the resultset, FALSE at the end.
     */
    public function valid(): bool
    {
        return $this->current() !== false;
    }

    public function rewind(): void
    {
        // Does nothing, since the PDO result can only be traversed once.
    }

    /**
     * Returns one row from the resultset, or NULL if there are no more rows.
     *
     * @return array<string,string>|null
     */
    public function fetch(): ?array
    {
        return $this->next();
    }

    /**
     * Returns all rows from the resultset as an associative array.
     *
     * @return array<int,array>
     */
    public function fetchAll(): array
    {
        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns a column from the resultset.
     */
    public function fetchColumn(int $columnNumber = 0): string
    {
        return $this->statement->fetchColumn($columnNumber);
    }

    /**
     * Returns a column from all rows of the resultset.
     *
     * @return string[]
     */
    public function fetchColumnAll(int $columnNumber = 0): array
    {
        $result = [];
        while (($columnValue = $this->statement->fetchColumn($columnNumber)) !== false) {
            $result[] = $columnValue;
        }

        return $result;
    }

    /**
     * Returns all the rows represented in the given class.
     *
     * @return object[]
     */
    public function fetchAllClass(string $className): array
    {
        //@phpstan-ignore-next-line PHPStan is wrong, setFetchMode accepts a string too for FETCH_CLASS
        $this->statement->setFetchMode(PDO::FETCH_CLASS, $className);

        return $this->statement->fetchAll();
    }

    /**
     * Returns one row represented in the given class.
     */
    public function fetchClass(string $className): ?object
    {
        //@phpstan-ignore-next-line PHPStan is wrong, setFetchMode accepts a string too for FETCH_CLASS
        $this->statement->setFetchMode(PDO::FETCH_CLASS, $className);

        $result = $this->statement->fetch();

        if (false === $result) {
            return null;
        }

        return $result;
    }

    /**
     * Returns the number of rows affected by the last INSERT, DELETE or UPDATE statement.
     */
    public function getAffectedRowCount(): int
    {
        return $this->statement->rowCount();
    }

    public function getPdoStatement(): PDOStatement
    {
        return $this->statement;
    }
}
