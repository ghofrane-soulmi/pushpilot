<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;
use App\Support\Ssh\Shell;

/**
 * Step 9: the zero-downtime switch. A new symlink is created next to
 * `current` and renamed over it; rename(2) is atomic, so every request sees
 * either the old release or the new one, never a missing or partial link.
 */
final class ActivateRelease extends Step
{
    public function name(): string
    {
        return 'activate';
    }

    public function label(): string
    {
        return 'Activate release';
    }

    public function run(DeploymentContext $context): void
    {
        $context->run(
            'cd '.$context->quoted()
            .' && ln -nfs '.Shell::quote('releases/'.$context->releaseName).' current.tmp'
            .' && mv -Tf current.tmp current',
            'Could not switch the current release',
        );
    }
}
