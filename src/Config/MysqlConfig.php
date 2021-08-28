<?php
declare(strict_types=1);

namespace Szemul\Database\Config;

use JetBrains\PhpStorm\Immutable;

/** @codeCoverageIgnore */
#[Immutable]
class MysqlConfig
{
    public function __construct(
        private AccessConfig $access,
        private GenericOptions $genericOptions,
        private string $database,
        private string $charset = 'utf8mb4',
        private ?string $timezone = null,
        private bool $useTraditionalStrictMode = false,
        private bool $retryOnMysqlServerHasGoneAway = false,
        private bool $persistent = false,
    ) {
    }

    public function getAccess(): AccessConfig
    {
        return $this->access;
    }

    public function getGenericOptions(): GenericOptions
    {
        return $this->genericOptions;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function useTraditionalStrictMode(): bool
    {
        return $this->useTraditionalStrictMode;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function retryOnMysqlServerHasGoneAway(): bool
    {
        return $this->retryOnMysqlServerHasGoneAway;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }
}
