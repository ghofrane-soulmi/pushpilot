<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;
use App\Domain\Deployments\Pipeline\FailurePolicy;
use App\Support\Ssh\Shell;

/**
 * Step 12 (REL-3): keep the newest N releases and never the live one.
 * Release names are UTC timestamps, so a reverse sort is newest first.
 */
final class CleanupReleases extends Step
{
    public function name(): string
    {
        return 'cleanup';
    }

    public function label(): string
    {
        return 'Remove old releases';
    }

    public function onFailure(): FailurePolicy
    {
        return FailurePolicy::Warn;
    }

    public function run(DeploymentContext $context): void
    {
        $keep = max(1, $context->project->keep_releases);

        $context->run(
            'cd '.$context->quoted('releases')
            .' && ls -1 | grep -E '.Shell::quote('^[0-9]{14}$').' | sort -r | tail -n +'.($keep + 1)
            .' | grep -vxF '.Shell::quote($context->releaseName)
            .' | while IFS= read -r release; do echo "Removing $release"; rm -rf -- "$release"; done',
            'Could not remove old releases',
        );
    }
}
