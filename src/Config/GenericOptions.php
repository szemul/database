<?php
declare(strict_types=1);

namespace Szemul\Database\Config;

/** @codeCoverageIgnore  */
class GenericOptions
{
    /** @param array<string,mixed> $pdoOptions */
    public function __construct(
        private string $connectionName = 'generic',
        private string $paramPrefix = '',
        private array $pdoOptions = [],
    ) {
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function getParamPrefix(): string
    {
        return $this->paramPrefix;
    }

    /** @return array<string,mixed> */
    public function getPdoOptions(): array
    {
        return $this->pdoOptions;
    }
}
