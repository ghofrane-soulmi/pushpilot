<?php

namespace App\Domain\Servers\Actions;

use App\Domain\Servers\Enums\ServerStatus;
use App\Domain\Servers\Models\Server;
use App\Domain\Servers\Requirements\ServerRequirements;
use App\Support\Ssh\CommandResult;
use App\Support\Ssh\Exceptions\SshException;
use App\Support\Ssh\SshClient;

/**
 * SRV-4: detect git, php, composer, node/npm and the PHP-FPM service on the
 * server and store the report. Pushpilot checks; it never installs anything.
 *
 * @phpstan-import-type Report from ServerRequirements
 */
final class CheckServerRequirements
{
    private const TIMEOUT_SECONDS = 15;

    public function __construct(private readonly SshClient $ssh) {}

    /**
     * Connection and authentication failures propagate as SshException; the
     * server is marked unreachable first.
     *
     * @return Report
     */
    public function handle(Server $server): array
    {
        try {
            $this->ssh->connect($server->credentials());

            $results = array_map(
                fn (string $command): CommandResult => $this->ssh->run($command, timeoutSeconds: self::TIMEOUT_SECONDS),
                ServerRequirements::commands(),
            );
        } catch (SshException $e) {
            $server->status = ServerStatus::Unreachable;
            $server->last_checked_at = now()->toImmutable();
            $server->save();

            throw $e;
        } finally {
            $this->ssh->disconnect();
        }

        $report = ServerRequirements::evaluate($results);

        $server->requirements = $report;
        $server->status = ServerStatus::Reachable;
        $server->last_checked_at = now()->toImmutable();
        $server->save();

        return $report;
    }
}
