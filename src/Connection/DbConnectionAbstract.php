<?php
declare(strict_types=1);

namespace Szemul\Database\Connection;

use DateTimeInterface;
use PDO;
use PDOException;
use PDOStatement;
use Szemul\Database\Config\GenericOptions;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Database\Debugging\DatabaseStartEvent;
use Szemul\Database\Exception\ConnectionException;
use Szemul\Database\Exception\QueryException;
use Szemul\Database\Result\QueryResult;
use Szemul\Debugger\DebuggerInterface;

abstract class DbConnectionAbstract
{
    protected ?PDO               $connection;
    protected ?DebuggerInterface $debugger          = null;
    protected int                $transactionCount  = 0;
    protected bool               $transactionFailed = false;

    public function __construct(protected GenericOptions $genericOptions)
    {
    }

    /**
     * @throws ConnectionException
     */
    public function connect(): void
    {
        try {
            $this->connectToDatabase();
        } catch (PDOException $e) {
            $message = $e->getMessage();
            $code    = (int)$e->getCode();
            $this->parsePdoExceptionMessage($message, $code);

            throw new ConnectionException($message, $code, $e);
        }
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function disconnect(): void
    {
        $this->connection = null;
    }

    public function getParamPrefix(): string
    {
        return $this->genericOptions->getParamPrefix();
    }

    public function setDebugger(?DebuggerInterface $debugger): static
    {
        $this->debugger = $debugger;

        return $this;
    }

    /**
     * @param array<string|int, mixed> $params
     *
     * @throws QueryException   On execution errors.
     */
    public function query(string $query, array $params = []): QueryResult
    {
        if (empty($this->connection)) {
            $this->connect();
        }

        try {
            if (null === $this->debugger) {
                $statement = $this->prepareStatement($query, $params);
                $statement->execute();
            } else {
                $debugEvent = new DatabaseStartEvent(
                    $this->getBackendType(),
                    $this->genericOptions->getConnectionName(),
                    $query,
                    $params,
                );
                $this->debugger->handleEvent($debugEvent);

                $statement = $this->prepareStatement($query, $params);

                try {
                    $statement->execute();
                } catch (PDOException $e) {
                    $this->debugger->handleEvent(new DatabaseCompleteEvent($debugEvent, $e));

                    throw $e;
                }

                $this->debugger->handleEvent(new DatabaseCompleteEvent($debugEvent));
            }

            return new QueryResult($statement);
        } catch (PDOException $e) {
            $this->transactionFailed = true;

            $message = $e->getMessage();
            $code    = (int)$e->getCode();
            $this->parsePdoExceptionMessage($message, $code);

            throw new QueryException($message, $code, $e);
        }
    }

    /**
     * @param array<string|int,mixed> $params
     */
    private function prepareStatement(string $query, array $params): PDOStatement
    {
        $statement = $this->connection->prepare($query);

        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $this->genericOptions->getParamPrefix() . $key, $value);
        }

        return $statement;
    }

    /**
     * Runs a paginated query, and returns the result.
     *
     * You can't use LIMIT or OFFSET clause in your query, because then it will be duplicated in the method.
     *
     * Be warned! You have to write the SELECT keyword in uppercase in order to work properly.
     *
     * @param array<string|int,mixed> $params
     *
     * @throws QueryException   On execution errors.
     */
    public function queryPaged(
        string $query,
        array $params,
        int $pageNumber,
        int $itemsPerPage,
        ?int &$itemCount = null,
    ): QueryResult {
        if (null !== $itemCount) {
            $query = preg_replace('#SELECT#', '$0 SQL_CALC_FOUND_ROWS', $query, 1);
        }

        $query .= '
            LIMIT
                ' . (int)$itemsPerPage . '
            OFFSET
                ' . (int)(($pageNumber - 1) * $itemsPerPage);

        $result = $this->query($query, $params);

        if (null !== $itemCount) {
            $itemCount = (int)$this->query('SELECT FOUND_ROWS()')
                ->fetchColumn();
        }

        return $result;
    }

    /**
     * Begins a transaction.
     *
     * If there already is an open transaction, it just increments the transaction counter.
     */
    public function beginTransaction(): int
    {
        if (empty($this->connection)) {
            $this->connect();
        }
        if (0 == $this->transactionCount) {
            $this->connection->beginTransaction();
            $this->transactionFailed = false;
        }

        return ++$this->transactionCount;
    }

    /**
     * Completes (commits or rolls back) a transaction.
     *
     * If there is more then 1 open transaction, it only decrements the transaction count by one, and returns the
     * current transaction status. It is possible for these transactions to fail and be eventually rolled back,
     * if any further statements fail.
     *
     * @return bool   TRUE if the transaction was committed, FALSE if it was rolled back.
     */
    public function completeTransaction(): bool
    {
        if (empty($this->connection)) {
            $this->connect();
        }
        $this->transactionCount--;
        if (0 == $this->transactionCount) {
            if ($this->transactionFailed) {
                $this->connection->rollBack();

                return false;
            } else {
                return $this->connection->commit();
            }
        }

        return $this->transactionFailed;
    }

    /**
     * Sets a transaction's status to failed.
     */
    public function failTransaction(): void
    {
        $this->transactionFailed = true;
    }

    /**
     * @throws QueryException
     *
     * @see PDO::lastInsertId()
     */
    public function lastInsertId(?string $name = null): string
    {
        if (empty($this->connection)) {
            $this->connect();
        }

        try {
            return $this->connection->lastInsertId($name);
        } catch (PDOException $e) {
            $message = $e->getMessage();
            $code    = (int)$e->getCode();
            $this->parsePdoExceptionMessage($message, $code);

            throw new QueryException($message, $code, $e);
        }
    }

    /**
     * Parses the message and code from the specified PDOException message.
     */
    protected function parsePdoExceptionMessage(string &$message, int &$code): void
    {
        $matches = [];

        // Parse the ANSI error code from the message.
        // Regex is based on the one from samuelelliot+php dot net at gmail dot com.
        if (strstr($message, 'SQLSTATE[') && preg_match('/SQLSTATE\[(\d+)]: (.+)$/', $message, $matches)) {
            $message = $matches[2];
            $code    = (int)$matches[1];
        }
    }

    /**
     * Returns the provided string, with all wildcard characters escaped.
     *
     * This method should be used to escape the string part in the "LIKE 'string' ESCAPE 'escapeCharacter'" statement.
     */
    public function escapeWildcards(string $string, string $escapeCharacter = '\\'): string
    {
        return preg_replace(
            '/([_%' . preg_quote($escapeCharacter, '/') . '])/',
            addcslashes($escapeCharacter, '$\\') . '$1',
            $string,
        );
    }

    /**
     * Returns the specified datetime as a date-time string usable by the current db connection type
     * or returns Null if NULL is sent
     */
    public function getFormattedDateTime(?DateTimeInterface $dateTime): ?string
    {
        return null === $dateTime ? null : $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * Returns the specified datetime as a date string usable by the current db connection type
     * or returns Null if NULL is sent
     */
    public function getFormattedDate(?DateTimeInterface $dateTime): ?string
    {
        return null === $dateTime ? null : $dateTime->format('Y-m-d');
    }

    /**
     * Returns the underlying PDO connection
     */
    public function getConnection(): PDO
    {
        if (empty($this->connection)) {
            $this->connect();
        }

        return $this->connection;
    }

    abstract protected function connectToDatabase(): void;

    abstract protected function getBackendType(): string;
}
