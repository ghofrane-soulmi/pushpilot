<?php

namespace App\Support\Ssh;

use Closure;
use LogicException;

/**
 * phpseclib 3 implementation of SshClient.
 *
 * Intentionally empty in Milestone 1; implemented in Milestone 2 (Servers:
 * key generation, test connection, requirements check).
 */
final class PhpseclibSshClient implements SshClient
{
    public function connect(SshCredentials $credentials): void
    {
        throw new LogicException('PhpseclibSshClient is implemented in Milestone 2.');
    }

    public function run(string $command, ?Closure $onOutput = null, int $timeoutSeconds = 600): CommandResult
    {
        throw new LogicException('PhpseclibSshClient is implemented in Milestone 2.');
    }

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return false;
    }
}
