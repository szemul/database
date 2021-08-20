<?php
declare(strict_types=1);

namespace Szemul\Database\Debugging;

use Szemul\Debugger\Event\DebugEventInterface;

class DatabaseStartEvent implements DebugEventInterface
{
    private float $timestamp;

    /** @param array<string|int,mixed> $params */
    public function __construct(
        private string $backendType,
        private string $connectionName,
        private string $query,
        private array $params = [],
    ) {
        $this->timestamp = microtime(true);
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getBackendType(): string
    {
        return $this->backendType;
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /** @return array<string|int,mixed> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function __toString(): string
    {
        return 'Database query started: ' . $this->query;
    }
}
