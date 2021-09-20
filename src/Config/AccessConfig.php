<?php
declare(strict_types=1);

namespace Szemul\Database\Config;

use JetBrains\PhpStorm\Immutable;

/** @codeCoverageIgnore */
#[Immutable]
class AccessConfig
{
    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private int $port,
    ) {
    }

    public function __debugInfo(): ?array
    {
        return [
            'host'     => $this->host,
            'username' => '*** REDACTED ***',
            'password' => '*** REDACTED ***',
            'port'     => $this->port,
        ];
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
