<?php
declare(strict_types=1);

namespace Szemul\Database\Connection;

use PDO;
use PDOException;
use Szemul\Database\Exception\QueryException;
use Szemul\Database\Result\QueryResult;

class MysqlConnection extends DbConnection
{
    public const CONNECTION_TYPE = 'mysql';

    protected bool $retryOnMysqlServerHasGoneAway = false;

    /**
     * @throws PDOException   On connection errors.
     */
    protected function connect(array $configuration): void
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;port=%d',
            $configuration['host'],
            $configuration['database'],
            $configuration['port'] ?? 3306,
        );

        $options                       = (array)($configuration['options'] ?? []);
        $options[PDO::ATTR_PERSISTENT] = (bool)($configuration['isPersistent'] ?? false);

        $this->connection = new PDO($dsn, $configuration['user'], $configuration['password'], $options);

        $this->query('SET NAMES ' . $configuration['charset'] ?? 'utf8mb4');

        if ($configuration['useTraditionalStrictMode'] ?? false) {
            $this->query('SET @@SESSION.sql_mode = \'TRADITIONAL\'; ');
        }

        if (isset($configuration['timezone'])) {
            $this->query('SET time_zone=:' . $this->paramPrefix . 'tz', ['tz' => $configuration['timezone']]);
        }

        $this->retryOnMysqlServerHasGoneAway = (bool)($configuration['retryOnMysqlServerHasGoneAway'] ?? false);
    }

    protected function getBackendType(): string
    {
        return self::CONNECTION_TYPE;
    }

    public function query(string $query, array $params = []): QueryResult
    {
        // If retryOnMysqlServerHasGoneAway is TRUE and we receive that error we reconnect and retry the query once
        try {
            return parent::query($query, $params);
        } catch (QueryException $e) {
            if (!$this->retryOnMysqlServerHasGoneAway) {
                throw $e;
            }

            if ($e->getMessage() === 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away') {
                $this->connect($this->configuration);
            }

            return parent::query($query, $params);
        }
    }
}
