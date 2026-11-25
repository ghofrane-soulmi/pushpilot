<?php

namespace App\Domain\Deployments\Actions;

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;

/**
 * Spec 9.3: a crashed worker must not leave a deployment "running" forever.
 * Runs every minute from the scheduler.
 */
final class FailStaleDeployments
{
    /**
     * Extra time after the job's own timeout before we call it dead.
     */
    private const GRACE_SECONDS = 300;

    public function handle(): int
    {
        $globalTimeout = (int) config('pushpilot.deployments.global_timeout');
        $queueWait = (int) config('pushpilot.deployments.queue_wait');

        $running = Deployment::query()
            ->where('status', DeploymentStatus::Running)
            ->where('started_at', '<', now()->subSeconds($globalTimeout + self::GRACE_SECONDS))
            ->get();

        $queued = Deployment::query()
            ->where('status', DeploymentStatus::Queued)
            ->where('created_at', '<', now()->subSeconds($queueWait + self::GRACE_SECONDS))
            ->get();

        foreach ($running as $deployment) {
            $this->fail($deployment, 'The worker stopped before the deployment finished (crash or restart). Check the log to see which step last ran.');
        }

        foreach ($queued as $deployment) {
            $this->fail($deployment, 'The deployment was never picked up by a worker. Check that Horizon is running.');
        }

        return $running->count() + $queued->count();
    }

    private function fail(Deployment $deployment, string $message): void
    {
        $deployment->status = DeploymentStatus::Failed;
        $deployment->error_message = $message;
        $deployment->current_step = null;
        $deployment->finished_at = now()->toImmutable();
        $deployment->save();
    }
}
