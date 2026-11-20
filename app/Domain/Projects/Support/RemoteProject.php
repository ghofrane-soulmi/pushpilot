<?php

namespace App\Domain\Projects\Support;

use App\Domain\Projects\Models\Project;
use App\Support\Ssh\Shell;

/**
 * Builds the shell commands that touch a project's directory on its server.
 * Every value is validated on input and quoted here with Shell::quote().
 */
final readonly class RemoteProject
{
    public function __construct(private Project $project) {}

    public function root(): string
    {
        return Shell::quote($this->project->deploy_path);
    }

    public function path(string $relative): string
    {
        return Shell::quote(rtrim($this->project->deploy_path, '/').'/'.$relative);
    }

    /**
     * Create the deploy path if needed and fail clearly when it is not writable.
     */
    public function ensureWritableRootCommand(): string
    {
        $root = $this->root();

        return "mkdir -p {$root} && test -w {$root}";
    }

    /**
     * releases/, shared/ and every shared path (dirs created, files touched).
     */
    public function createStructureCommand(): string
    {
        $directories = [
            $this->path('releases'),
            $this->path('shared'),
        ];
        $files = [];

        foreach ($this->project->shared_paths as $sharedPath) {
            if (str_ends_with($sharedPath, '/')) {
                $directories[] = $this->path('shared/'.rtrim($sharedPath, '/'));
            } else {
                $parent = dirname($sharedPath);
                if ($parent !== '.') {
                    $directories[] = $this->path('shared/'.$parent);
                }
                $files[] = $this->path('shared/'.$sharedPath);
            }
        }

        // Laravel needs these inside a shared storage/ directory.
        if (in_array('storage/', $this->project->shared_paths, true)) {
            foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
                $directories[] = $this->path('shared/storage/'.$dir);
            }
        }

        $command = 'mkdir -p '.implode(' ', array_unique($directories));

        if ($files !== []) {
            $command .= ' && touch '.implode(' ', array_unique($files));
        }

        return $command;
    }

    /**
     * Write the deploy private key to ~/.ssh/pushpilot_<slug> (mode 600).
     */
    public function installDeployKeyCommand(): string
    {
        $keyPath = $this->project->remoteDeployKeyPath();
        $key = Shell::quote(rtrim($this->project->deploy_private_key)."\n");

        return 'mkdir -p "$HOME/.ssh" && chmod 700 "$HOME/.ssh"'
            ." && umask 077 && printf '%s' {$key} > {$keyPath}.tmp"
            ." && mv -f {$keyPath}.tmp {$keyPath} && chmod 600 {$keyPath}";
    }

    /**
     * Check that the deploy key can read the repository and the branch exists.
     */
    public function verifyRepositoryCommand(): string
    {
        return $this->project->gitSshCommand()
            .' git ls-remote --exit-code --heads '
            .Shell::quote($this->project->repository_url).' '
            .Shell::quote('refs/heads/'.$this->project->branch);
    }

    /**
     * Atomically replace shared/.env (mode 640). Content travels base64-encoded
     * so no character can break out of the command.
     */
    public function writeEnvCommand(string $content): string
    {
        $target = $this->path('shared/.env');
        $temporary = $this->path('shared/.env.pushpilot-tmp');
        $encoded = Shell::quote(base64_encode($content));

        return 'mkdir -p '.$this->path('shared')
            ." && umask 027 && printf '%s' {$encoded} | base64 -d > {$temporary}"
            ." && chmod 640 {$temporary} && mv -f {$temporary} {$target}";
    }
}
