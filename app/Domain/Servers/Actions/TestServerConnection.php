<?php

namespace App\Domain\Servers\Actions;

use App\Domain\Servers\Enums\ServerStatus;
use App\Domain\Servers\Models\Server;
use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshException;
use App\Support\Ssh\SshClient;

/**
 * SRV-3: connect, run `whoami`, report a human-readable result and update the
 * reachability badge (SRV-6).
 */
final class TestServerConnection
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(private readonly SshClient $ssh) {}

    public function handle(Server $server): ConnectionTestResult
    {
        try {
            $this->ssh->connect($server->credentials());
            $result = $this->ssh->run('whoami', timeoutSeconds: self::TIMEOUT_SECONDS);
        } catch (SshAuthenticationException) {
            return $this->record($server, false, sprintf(
                'Authentication failed: the server rejected Pushpilot\'s key for "%s". Add the public key to ~/.ssh/authorized_keys of that user.',
                $server->username,
            ));
        } catch (SshException $e) {
            return $this->record($server, false, $e->getMessage());
        } finally {
            $this->ssh->disconnect();
        }

        if ($result->failed()) {
            return $this->record($server, false, "Connected, but `whoami` failed (exit code {$result->exitCode}).");
        }

        return $this->record($server, true, 'Connected as '.trim($result->output).'.');
    }

    private function record(Server $server, bool $successful, string $message): ConnectionTestResult
    {
        $server->status = $successful ? ServerStatus::Reachable : ServerStatus::Unreachable;
        $server->last_checked_at = now()->toImmutable();
        $server->save();

        return new ConnectionTestResult($successful, $message);
    }
}
