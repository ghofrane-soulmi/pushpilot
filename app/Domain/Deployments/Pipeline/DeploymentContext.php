<?php

namespace App\Domain\Deployments\Pipeline;

use App\Domain\Deployments\Exceptions\StepFailedException;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Projects\Models\Project;
use App\Support\Ssh\CommandResult;
use App\Support\Ssh\OutputStream;
use App\Support\Ssh\Shell;
use App\Support\Ssh\SshClient;

/**
 * What a step needs: the deployment, its project, release paths and a way to
 * run commands on the server with logging and the current time budget.
 */
final class DeploymentContext
{
    private int $timeoutSeconds;

    public function __construct(
        public readonly Deployment $deployment,
        public readonly Project $project,
        private readonly SshClient $ssh,
        public readonly DeploymentLogger $logger,
        public readonly string $releaseName,
        int $timeoutSeconds,
    ) {
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Set the time budget for the next commands (step timeout, capped by
     * what is left of the global timeout).
     */
    public function setTimeout(int $seconds): void
    {
        $this->timeoutSeconds = max(1, $seconds);
    }

    public function timeout(): int
    {
        return $this->timeoutSeconds;
    }

    /**
     * Absolute path inside the deploy path, unquoted.
     */
    public function path(string $relative = ''): string
    {
        $root = rtrim($this->project->deploy_path, '/');

        return $relative === '' ? $root : $root.'/'.ltrim($relative, '/');
    }

    /**
     * Quoted path inside the deploy path, ready for a command.
     */
    public function quoted(string $relative = ''): string
    {
        return Shell::quote($this->path($relative));
    }

    public function releasePath(): string
    {
        return $this->path('releases/'.$this->releaseName);
    }

    public function quotedReleasePath(): string
    {
        return Shell::quote($this->releasePath());
    }

    /**
     * Run a command on the server, streaming masked output to the log.
     *
     * @throws StepFailedException when the command exits non-zero
     */
    public function run(string $command, string $failureMessage = 'The command failed'): CommandResult
    {
        $result = $this->ssh->run(
            $command,
            fn (OutputStream $stream, string $chunk) => $this->logger->write($stream, $chunk),
            $this->timeoutSeconds,
        );

        $this->logger->flush();

        if ($result->failed()) {
            throw new StepFailedException("{$failureMessage} (exit code {$result->exitCode}).");
        }

        return $result;
    }

    /**
     * Run a command and return its output without logging it.
     */
    public function capture(string $command): CommandResult
    {
        return $this->ssh->run($command, timeoutSeconds: $this->timeoutSeconds);
    }
}
