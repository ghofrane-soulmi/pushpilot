<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;
use App\Domain\Deployments\Pipeline\DeploymentStep;
use App\Domain\Deployments\Pipeline\FailurePolicy;

/**
 * Defaults shared by most steps: always runs, and a failure removes the release.
 */
abstract class Step implements DeploymentStep
{
    public function shouldRun(DeploymentContext $context): bool
    {
        return true;
    }

    public function onFailure(): FailurePolicy
    {
        return FailurePolicy::AbortAndRemoveRelease;
    }

    /**
     * "cd <release> && <command>": run inside the new release directory.
     */
    protected function inRelease(DeploymentContext $context, string $command): string
    {
        return 'cd '.$context->quotedReleasePath().' && '.$command;
    }
}
