<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;

/**
 * Step 5 (PRJ-5): only when npm build is enabled.
 */
final class BuildAssets extends Step
{
    public function name(): string
    {
        return 'assets';
    }

    public function label(): string
    {
        return 'Build front-end assets';
    }

    public function shouldRun(DeploymentContext $context): bool
    {
        return $context->project->run_npm_build;
    }

    public function run(DeploymentContext $context): void
    {
        $context->run(
            $this->inRelease($context, 'npm ci --no-audit --no-fund && npm run build'),
            'The asset build failed',
        );
    }
}
