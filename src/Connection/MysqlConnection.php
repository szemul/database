<?php
declare(strict_types=1);

namespace Szemul\Database\Connection;

use JetBrains\PhpStorm\Pure;
use PDO;
use PDOException;
use Szemul\Database\Config\MysqlConfig;
use Szemul\Database\Exception\QueryException;
use Szemul\Database\Exception\ServerHasGoneAwayException;
use Szemul\Database\Helper\MysqlErrorHelper;
use Szemul\Database\Result\QueryResult;

class MysqlConnection extends DbConnectionAbstract
{
    public const BACKEND_TYPE = 'mysql';

    #[Pure]
    public function __construct(
        protected MysqlConfig $config,
        protected MysqlErrorHelper $errorHelper,
    ) {
        parent::__construct($config->getGenericOptions());
    }

    /**
     * @throws PDOException   On connection errors.
     */
    protected function connectToDatabase(): void
    {
        $accessConfig   = $this->config->getAccess();
        $genericOptions = $this->config->getGenericOptions();

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;port=%d',
            $accessConfig->getHost(),
            $this->config->getDatabase(),
            $accessConfig->getPort(),
        );

        $options                       = $genericOptions->getPdoOptions();
        $options[PDO::ATTR_PERSISTENT] = $this->config->isPersistent();

        $this->connection = new PDO(
            $dsn,
            $accessConfig->getUsername(),
            $accessConfig->getPassword(),
            $options,
        );

        $this->query('SET NAMES ' . $this->config->getCharset());

        if ($this->config->useTraditionalStrictMode()) {
            $this->query('SET @@SESSION.sql_mode = \'TRADITIONAL\'; ');
        }

        if (null !== $this->config->getTimezone()) {
            $this->query(
                'SET time_zone=:' . $this->genericOptions->getParamPrefix() . 'tz',
                ['tz' => $this->config->getTimezone()],
            );
        }
    }

    protected function getBackendType(): string
    {
        return self::BACKEND_TYPE;
    }

    public function query(string $query, array $params = []): QueryResult
    {
        try {
            return parent::query($query, $params);
        } catch (QueryException $e) {
            try {
                $this->errorHelper->processException($e);
            } catch (ServerHasGoneAwayException $serverHasGoneAwayException) {
                return $this->handleServerHasGoneAway($serverHasGoneAwayException, $query, $params);
            }
        }
    }

    /**
     * If retryOnMysqlServerHasGoneAway is TRUE and we receive that error we reconnect and retry the query once
     *
     * @param array<string|int, mixed> $params
     */
    private function handleServerHasGoneAway(ServerHasGoneAwayException $exception, string $query, array $params): QueryResult
    {
        if (!$this->config->retryOnMysqlServerHasGoneAway()) {
            throw $exception;
        }

        $this->connect();

        try {
            return parent::query($query, $params);
        } catch (QueryException $exception) {
            $this->errorHelper->processException($exception);
        }
    }
}
