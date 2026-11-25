<?php

namespace App\Domain\Deployments\Jobs;

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Deployments\Pipeline\Pipeline;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * DEP-4: runs one deployment on the dedicated "deployments" queue.
 *
 * A project lock (WithoutOverlapping) keeps one deployment running per
 * project; a second one waits in the queue until the first finishes. Being
 * released while waiting is not a retry: steps are never re-run (spec 9.3),
 * so any exception fails the job immediately.
 */
final class DeployJob implements ShouldQueue
{
    use Queueable;

    /**
     * Unlimited attempts while waiting for the lock, bounded by retryUntil().
     */
    public int $tries = 0;

    /**
     * Never re-run a deployment that threw.
     */
    public int $maxExceptions = 1;

    public bool $failOnTimeout = true;

    public int $timeout;

    public function __construct(public readonly Deployment $deployment)
    {
        $this->timeout = (int) config('pushpilot.deployments.global_timeout') + 120;
        $this->onConnection('deployments');
        $this->onQueue((string) config('pushpilot.deployments.queue'));
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('project:'.$this->deployment->project_id))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function retryUntil(): CarbonImmutable
    {
        return now()->toImmutable()->addSeconds((int) config('pushpilot.deployments.queue_wait'));
    }

    public function handle(Pipeline $pipeline): void
    {
        $deployment = $this->deployment->fresh();

        // Cancelled (or otherwise finished) while it was waiting in the queue.
        if ($deployment === null || $deployment->status !== DeploymentStatus::Queued) {
            return;
        }

        $pipeline->run($deployment);
    }

    /**
     * The worker died, timed out or gave up waiting: never leave it "running".
     */
    public function failed(?Throwable $exception): void
    {
        $deployment = $this->deployment->fresh();

        if ($deployment === null || $deployment->status->isFinished()) {
            return;
        }

        $deployment->status = DeploymentStatus::Failed;
        $deployment->error_message = $deployment->started_at === null
            ? 'The deployment waited too long in the queue and was abandoned.'
            : 'The worker stopped before the deployment finished (timeout or crash). The live release was not switched unless the log shows "Activate release" completed.';
        $deployment->current_step = null;
        $deployment->finished_at = now()->toImmutable();
        $deployment->save();
    }
}
