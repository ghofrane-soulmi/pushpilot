<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;
use App\Domain\Deployments\Pipeline\FailurePolicy;
use App\Domain\Projects\Rules\ProjectPatterns;
use App\Support\Ssh\Shell;

/**
 * Step 10 (PRJ-7): reload PHP-FPM so OPcache picks up the new release, and
 * restart queue workers. The release is already live, so failures only warn.
 */
final class ReloadServices extends Step
{
    public function name(): string
    {
        return 'reload';
    }

    public function label(): string
    {
        return 'Reload PHP-FPM and queues';
    }

    public function shouldRun(DeploymentContext $context): bool
    {
        return $context->project->php_fpm_service !== null || $context->project->queue_restart;
    }

    public function onFailure(): FailurePolicy
    {
        return FailurePolicy::Warn;
    }

    public function run(DeploymentContext $context): void
    {
        $service = $context->project->php_fpm_service;

        if ($service !== null && ProjectPatterns::isPhpFpmService($service)) {
            $context->run(
                'sudo -n systemctl reload '.Shell::quote($service),
                "Could not reload {$service}. Check the sudoers line for the deploy user",
            );
        }

        if ($context->project->queue_restart) {
            $context->run(
                'cd '.$context->quoted('current').' && php artisan queue:restart',
                'php artisan queue:restart failed',
            );
        }
    }
}
