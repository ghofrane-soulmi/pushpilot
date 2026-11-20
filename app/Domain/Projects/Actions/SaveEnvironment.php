<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\EnvFile;
use App\Domain\Projects\Support\RemoteProject;
use App\Support\Ssh\Exceptions\SshException;
use App\Support\Ssh\SshClient;

/**
 * PRJ-4: store the .env encrypted and write it to shared/.env on the server.
 *
 * The encrypted copy in Pushpilot is the source of truth; when the project is
 * not initialized yet (or the server is unreachable) it is written later by
 * Initialize.
 */
final class SaveEnvironment
{
    public function __construct(private readonly SshClient $ssh) {}

    public function set(Project $project, string $key, string $value): ActionResult
    {
        return $this->save($project, EnvFile::parse($project->env_content)->set($key, $value));
    }

    public function remove(Project $project, string $key): ActionResult
    {
        return $this->save($project, EnvFile::parse($project->env_content)->remove($key));
    }

    public function replace(Project $project, string $content): ActionResult
    {
        return $this->save($project, EnvFile::parse($content));
    }

    /**
     * Upload the stored .env to the server.
     */
    public function sync(Project $project): ActionResult
    {
        if ($project->initialized_at === null) {
            return new ActionResult(false, 'Saved in Pushpilot. It will be written to the server when you initialize the project.');
        }

        try {
            $this->ssh->connect($project->server->credentials());
            $result = $this->ssh->run(
                (new RemoteProject($project))->writeEnvCommand($project->env_content ?? ''),
                timeoutSeconds: 30,
            );
        } catch (SshException $e) {
            return new ActionResult(false, 'Saved in Pushpilot, but not written to the server: '.$e->getMessage());
        } finally {
            $this->ssh->disconnect();
        }

        if ($result->failed()) {
            return new ActionResult(false, "Saved in Pushpilot, but writing shared/.env failed (exit code {$result->exitCode}).");
        }

        $project->env_synced_at = now()->toImmutable();
        $project->save();

        return new ActionResult(true, 'Saved and written to shared/.env on the server.');
    }

    private function save(Project $project, EnvFile $file): ActionResult
    {
        $project->env_content = $file->toString();
        $project->env_synced_at = null;
        $project->save();

        return $this->sync($project);
    }
}
