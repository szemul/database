<?php
declare(strict_types=1);

namespace Szemul\Database\Test\Mock;

use JetBrains\PhpStorm\Pure;
use PDO;
use PDOException;
use Szemul\Database\Config\GenericOptions;
use Szemul\Database\Connection\DbConnectionAbstract;

class MockDbConnection extends DbConnectionAbstract
{
    public const BACKEND_TYPE = 'mock';

    protected ?PDO       $connection          = null;
    public ?PDOException $connectionException = null;

    #[Pure]
    public function __construct(private PDO $pdoMock, GenericOptions $genericOptions)
    {
        parent::__construct($genericOptions);
    }

    protected function connectToDatabase(): void
    {
        if (null !== $this->connectionException) {
            throw $this->connectionException;
        }

        $this->connection = $this->pdoMock;
    }

    protected function getBackendType(): string
    {
        return self::BACKEND_TYPE;
    }

    public function getCurrentConnection(): ?PDO
    {
        return $this->connection;
    }
}
