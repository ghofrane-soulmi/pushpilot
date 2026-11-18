<?php

namespace App\Support\Ssh;

use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshConnectionException;
use App\Support\Ssh\Exceptions\SshException;
use App\Support\Ssh\Exceptions\SshTimeoutException;
use Closure;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SSH2;
use RuntimeException;

/**
 * phpseclib 3 implementation of SshClient: pure PHP, no system `ssh` binary.
 *
 * stdout is streamed to the callback as it arrives; stderr is collected
 * separately and delivered once the command finishes.
 */
final class PhpseclibSshClient implements SshClient
{
    private ?SSH2 $ssh = null;

    public function __construct(private readonly int $connectTimeoutSeconds = 10) {}

    public function connect(SshCredentials $credentials): void
    {
        $this->disconnect();

        try {
            $key = PublicKeyLoader::load($credentials->privateKey());
        } catch (NoKeyLoadedException $e) {
            throw new SshException('The stored private key could not be loaded.', previous: $e);
        }

        if (! $key instanceof PrivateKey) {
            throw new SshException('The stored key is not a private key.');
        }

        $ssh = new SSH2($credentials->host, $credentials->port, $this->connectTimeoutSeconds);

        try {
            $authenticated = $ssh->login($credentials->username, $key);
        } catch (RuntimeException $e) {
            throw new SshConnectionException(
                "Could not connect to {$credentials->host}:{$credentials->port}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if (! $authenticated) {
            if (! $ssh->isConnected()) {
                throw new SshConnectionException("Could not connect to {$credentials->host}:{$credentials->port}.");
            }

            $ssh->disconnect();

            throw new SshAuthenticationException(
                "Authentication failed: {$credentials->host} rejected the key for user \"{$credentials->username}\".",
            );
        }

        $this->ssh = $ssh;
    }

    public function run(string $command, ?Closure $onOutput = null, int $timeoutSeconds = 600): CommandResult
    {
        $ssh = $this->ssh;

        if ($ssh === null || ! $ssh->isAuthenticated()) {
            throw new SshException('Cannot run a command without an open SSH session.');
        }

        $ssh->setTimeout($timeoutSeconds);
        $ssh->enableQuietMode();

        $output = '';

        try {
            $finished = $ssh->exec($command, function (string $chunk) use (&$output, $onOutput): void {
                $output .= $chunk;

                if ($onOutput !== null) {
                    $onOutput(OutputStream::Stdout, $chunk);
                }
            });
        } catch (RuntimeException $e) {
            $this->disconnect();

            throw new SshConnectionException('The SSH connection was lost: '.$e->getMessage(), previous: $e);
        }

        if ($ssh->isTimeout()) {
            $this->disconnect();

            throw new SshTimeoutException("The command did not finish within {$timeoutSeconds} seconds.");
        }

        if ($finished === false) {
            $this->disconnect();

            throw new SshConnectionException('The SSH connection was lost while running a command.');
        }

        $errorOutput = (string) $ssh->getStdError();

        if ($onOutput !== null && $errorOutput !== '') {
            $onOutput(OutputStream::Stderr, $errorOutput);
        }

        $exitStatus = $ssh->getExitStatus();

        return new CommandResult(is_int($exitStatus) ? $exitStatus : -1, $output, $errorOutput);
    }

    public function disconnect(): void
    {
        $this->ssh?->disconnect();
        $this->ssh = null;
    }

    public function isConnected(): bool
    {
        return $this->ssh !== null && $this->ssh->isAuthenticated();
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
