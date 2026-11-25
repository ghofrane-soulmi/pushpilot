<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;

/**
 * Step 8: cache config, routes, views and events for the new release.
 */
final class OptimizeApplication extends Step
{
    public function name(): string
    {
        return 'optimize';
    }

    public function label(): string
    {
        return 'Optimize application';
    }

    public function run(DeploymentContext $context): void
    {
        $context->run($this->inRelease($context, 'php artisan optimize'), 'php artisan optimize failed');
    }
}
