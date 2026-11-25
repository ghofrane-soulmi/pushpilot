<?php

namespace App\Domain\Deployments\Actions;

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;

/**
 * DEP-8: a queued deployment is cancelled at once; a running one stops before
 * its next step, and never after the release went live.
 */
final class CancelDeployment
{
    /**
     * @return bool false when the deployment had already finished
     */
    public function handle(Deployment $deployment): bool
    {
        if ($deployment->status === DeploymentStatus::Queued) {
            $deployment->status = DeploymentStatus::Cancelled;
            $deployment->error_message = 'Cancelled before it started.';
            $deployment->finished_at = now()->toImmutable();
            $deployment->save();

            return true;
        }

        if ($deployment->status === DeploymentStatus::Running) {
            $deployment->cancel_requested_at = now()->toImmutable();
            $deployment->save();

            return true;
        }

        return false;
    }
}
