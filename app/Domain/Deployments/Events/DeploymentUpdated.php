<?php

namespace App\Domain\Deployments\Events;

use App\Domain\Deployments\Models\Deployment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A deployment's status, step or commit changed. Values are captured when the
 * event is created, so each broadcast reports the state at that moment.
 */
final class DeploymentUpdated implements ShouldBroadcastNow
{
    public readonly int $deploymentId;

    public readonly string $status;

    public readonly ?string $currentStep;

    public function __construct(Deployment $deployment)
    {
        $this->deploymentId = $deployment->id;
        $this->status = $deployment->status->value;
        $this->currentStep = $deployment->current_step;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('deployments.'.$this->deploymentId);
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    /**
     * @return array{id: int, status: string, current_step: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->deploymentId,
            'status' => $this->status,
            'current_step' => $this->currentStep,
        ];
    }
}
