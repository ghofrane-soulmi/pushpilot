<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;

/**
 * Step 1: make sure releases/ and shared/ exist and the new release name is free.
 */
final class PrepareRelease extends Step
{
    public function name(): string
    {
        return 'prepare';
    }

    public function label(): string
    {
        return 'Prepare release';
    }

    public function run(DeploymentContext $context): void
    {
        $context->run(
            'mkdir -p '.$context->quoted('releases').' '.$context->quoted('shared')
            .' && test ! -e '.$context->quotedReleasePath(),
            'Could not prepare the release directory',
        );
    }
}
