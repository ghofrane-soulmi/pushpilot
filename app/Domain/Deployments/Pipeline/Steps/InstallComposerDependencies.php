<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;

/**
 * Step 4 (PRJ-5).
 */
final class InstallComposerDependencies extends Step
{
    public function name(): string
    {
        return 'composer';
    }

    public function label(): string
    {
        return 'Install Composer dependencies';
    }

    public function shouldRun(DeploymentContext $context): bool
    {
        return $context->project->run_composer;
    }

    public function run(DeploymentContext $context): void
    {
        $context->run(
            $this->inRelease($context, 'composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress'),
            'composer install failed',
        );
    }
}
