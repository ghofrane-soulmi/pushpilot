<?php

namespace App\Support\Ssh;

use SensitiveParameter;

/**
 * Everything needed to open an SSH session. The private key never appears in
 * var_dump(), logs or exception traces.
 */
final readonly class SshCredentials
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        #[SensitiveParameter] private string $privateKey,
    ) {}

    public function privateKey(): string
    {
        return $this->privateKey;
    }

    public function destination(): string
    {
        return "{$this->username}@{$this->host}:{$this->port}";
    }

    /**
     * @return array<string, string|int>
     */
    public function __debugInfo(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'privateKey' => '[redacted]',
        ];
    }
}
