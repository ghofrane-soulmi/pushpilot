<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;
use App\Domain\Deployments\Pipeline\FailurePolicy;

/**
 * Step 7 (PRJ-5): a failure stops the deployment, and the database may
 * already be partly migrated, so the user is warned.
 */
final class RunMigrations extends Step
{
    public function name(): string
    {
        return 'migrate';
    }

    public function label(): string
    {
        return 'Run database migrations';
    }

    public function shouldRun(DeploymentContext $context): bool
    {
        return $context->project->run_migrations;
    }

    public function onFailure(): FailurePolicy
    {
        return FailurePolicy::AbortWithDatabaseWarning;
    }

    public function run(DeploymentContext $context): void
    {
        $context->run(
            $this->inRelease($context, 'php artisan migrate --force --no-interaction'),
            'Migrations failed',
        );
    }
}
