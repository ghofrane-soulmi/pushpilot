<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\RemoteProject;
use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshException;
use App\Support\Ssh\SshClient;

/**
 * PRJ-10: prepare the server for zero-downtime deployments.
 *
 *   <deploy_path>/releases/   one directory per release
 *   <deploy_path>/shared/     .env, storage/ and other shared paths
 *   ~/.ssh/pushpilot_<slug>   the project's deploy key (read-only repo access)
 *
 * Idempotent: safe to run again. Finishes by checking that the deploy key can
 * read the repository branch.
 */
final class InitializeProject
{
    private const TIMEOUT_SECONDS = 60;

    /**
     * @var list<array{label: string, ok: bool, message: string|null}>
     */
    private array $steps = [];

    public function __construct(private readonly SshClient $ssh) {}

    public function handle(Project $project): ActionResult
    {
        $this->steps = [];
        $remote = new RemoteProject($project);

        try {
            $this->ssh->connect($project->server->credentials());
        } catch (SshAuthenticationException) {
            return $this->fail('Could not log in to the server: authorize the server key first (see the server page).');
        } catch (SshException $e) {
            return $this->fail('Could not connect to the server: '.$e->getMessage());
        }

        try {
            if (! $this->step('Deploy path is writable', $remote->ensureWritableRootCommand(),
                "{$project->server->username} cannot create or write to {$project->deploy_path}. Create it and give that user ownership, e.g. sudo mkdir -p {$project->deploy_path} && sudo chown {$project->server->username} {$project->deploy_path}")) {
                return $this->fail('The deploy path is not writable.');
            }

            if (! $this->step('Create releases/ and shared/', $remote->createStructureCommand(), 'Could not create the directory structure.')) {
                return $this->fail('Could not create the directory structure.');
            }

            if (! $this->step('Install the deploy key', $remote->installDeployKeyCommand(), 'Could not write the deploy key to ~/.ssh.')) {
                return $this->fail('Could not install the deploy key.');
            }

            $project->initialized_at = now()->toImmutable();

            if ($project->env_content !== null) {
                if ($this->step('Write shared/.env', $remote->writeEnvCommand($project->env_content), 'Could not write shared/.env.')) {
                    $project->env_synced_at = now()->toImmutable();
                }
            }

            $project->save();

            $repositoryReachable = $this->step(
                'Repository is readable with the deploy key',
                $remote->verifyRepositoryCommand(),
                "Add the deploy key to the repository (GitHub → Settings → Deploy keys) and check that branch \"{$project->branch}\" exists.",
            );
        } catch (SshException $e) {
            return $this->fail('The connection failed during initialization: '.$e->getMessage());
        } finally {
            $this->ssh->disconnect();
        }

        return $repositoryReachable
            ? new ActionResult(true, 'Project initialized. The server is ready for its first deployment.', $this->steps)
            : new ActionResult(false, 'Directories are ready, but the repository is not readable yet.', $this->steps);
    }

    private function step(string $label, string $command, string $failureHint): bool
    {
        $result = $this->ssh->run($command, timeoutSeconds: self::TIMEOUT_SECONDS);

        $this->steps[] = [
            'label' => $label,
            'ok' => $result->successful(),
            'message' => $result->successful() ? null : $failureHint,
        ];

        return $result->successful();
    }

    private function fail(string $message): ActionResult
    {
        return new ActionResult(false, $message, $this->steps);
    }
}
