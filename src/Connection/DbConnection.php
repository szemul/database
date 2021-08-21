<?php
declare(strict_types=1);

namespace Szemul\Database\Connection;

use DateTimeInterface;
use PDO;
use PDOException;
use PDOStatement;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Database\Debugging\DatabaseStartEvent;
use Szemul\Database\Exception\ConnectionException;
use Szemul\Database\Exception\QueryException;
use Szemul\Database\Result\QueryResult;
use Szemul\Debugger\DebuggerInterface;

abstract class DbConnection
{
    /** @var array<string,mixed> */
    protected array              $configuration     = [];
    protected ?PDO               $connection;
    protected ?DebuggerInterface $debugger          = null;
    protected int                $transactionCount  = 0;
    protected bool               $transactionFailed = false;

    /**
     * @param array<string,mixed> $configuration
     *
     * @throws ConnectionException
     */
    public function __construct(
        array $configuration,
        protected string $connectionName,
        protected string $paramPrefix = '',
    ) {
        $this->configuration = $configuration;

        try {
            $this->connect($configuration);
        } catch (PDOException $e) {
            $message = '';
            $code    = 0;
            $this->parsePdoException($e, $message, $code);

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
        return $this->paramPrefix;
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
            throw new ConnectionException('Connection to the database is not established');
        }

        try {
            if (null === $this->debugger) {
                $statement = $this->prepareStatement($query, $params);
                $statement->execute();
            } else {
                $debugEvent = new DatabaseStartEvent($this->getBackendType(), $this->connectionName, $query, $params);
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
            $message                 = '';
            $code                    = 0;
            $this->parsePdoException($e, $message, $code);

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
            $statement->bindValue(':' . $this->paramPrefix . $key, $value, $this->getParamType($value));
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
     * Returns the PDO data type for the specified value.
     *
     * Also casts the specified value if it's necessary.
     */
    protected function getParamType(mixed &$value): int
    {
        if (is_integer($value)) {
            return PDO::PARAM_INT;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } else {
            $value = (string)$value;

            return PDO::PARAM_STR;
        }
    }

    /**
     * Begins a transaction.
     *
     * If there already is an open transaction, it just increments the transaction counter.
     */
    public function beginTransaction(): int
    {
        if (empty($this->connection)) {
            throw new ConnectionException('Connection to the database is not established');
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
     *
     * @throws ConnectionException   If no database connection is established.
     */
    public function completeTransaction(): bool
    {
        if (empty($this->connection)) {
            throw new ConnectionException('Connection to the database is not established');
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
     * Returns the quoted version of the specified value.
     *
     * Do not use this function to quote data in a query, use the bound parameters instead. {@see self::query()}
     *
     * @throws ConnectionException   If no database connection is established.
     */
    public function quote(mixed $value): string
    {
        if (empty($this->connection)) {
            throw new ConnectionException('Connection to the database is not established');
        }

        return $this->connection->quote($value, $this->getParamType($value));
    }

    /**
     * @throws ConnectionException
     * @throws QueryException
     *
     * @see PDO::lastInsertId()
     */
    public function lastInsertId(?string $name = null): string
    {
        if (empty($this->connection)) {
            throw new ConnectionException('Connection to the database is not established');
        }

        try {
            return $this->connection->lastInsertId($name);
        } catch (PDOException $e) {
            $message = '';
            $code    = 0;
            $this->parsePdoException($e, $message, $code);

            throw new QueryException($message, $code, $e);
        }
    }

    /**
     * Parses the message and code from the specified PDOException.
     *
     * @param PDOException $exception The exception to parse
     * @param string       $message   The parsed message (outgoing param).
     * @param int          $code      The parsed code (outgoing param).
     */
    protected function parsePdoException(PDOException $exception, string &$message, int &$code): void
    {
        $message = $exception->getMessage();
        $code    = (int)$exception->getCode();
        $matches = [];

        // Parse the ANSI error code from the message.
        // Regex is based on the one from samuelelliot+php dot net at gmail dot com.
        if (strstr($message, 'SQLSTATE[') && preg_match('/SQLSTATE\[(\d+)\]: (.+)$/', $message, $matches)) {
            $message = $matches[2];
            $code    = $matches[1];
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
     * Returns the specified datetime as a date-time string usable by the current db connection type.
     */
    public function getFormattedDateTime(?DateTimeInterface $dateTime = null): string
    {
        if (null == $dateTime) {
            $dateTime = new \DateTime();
        }

        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * Returns the specified datetime as a date string usable by the current db connection type.
     */
    public function getDate(?DateTimeInterface $dateTime = null): string
    {
        if (null == $dateTime) {
            $dateTime = new \DateTime();
        }

        return $dateTime->format('Y-m-d');
    }

    /** @param array<string,mixed> $configuration */
    abstract protected function connect(array $configuration): void;

    abstract protected function getBackendType(): string;
}
