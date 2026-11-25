<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;
use App\Support\Ssh\Shell;

/**
 * Step 3: replace each shared path in the release with a symlink into shared/
 * (PRJ-3), so .env and storage/ survive across releases.
 */
final class LinkSharedPaths extends Step
{
    public function name(): string
    {
        return 'shared';
    }

    public function label(): string
    {
        return 'Link shared files';
    }

    public function shouldRun(DeploymentContext $context): bool
    {
        return $context->project->shared_paths !== [];
    }

    public function run(DeploymentContext $context): void
    {
        $commands = [];

        foreach ($context->project->shared_paths as $sharedPath) {
            $relative = rtrim($sharedPath, '/');
            $target = Shell::quote($context->releasePath().'/'.$relative);
            $source = $context->quoted('shared/'.$relative);
            $parent = dirname($relative);

            if ($parent !== '.') {
                $commands[] = 'mkdir -p '.Shell::quote($context->releasePath().'/'.$parent);
            }

            $commands[] = "rm -rf {$target}";
            $commands[] = "ln -nfs {$source} {$target}";
        }

        $context->run(implode(' && ', $commands), 'Could not link the shared files');
    }
}
