<?php

namespace App\Domain\Deployments\Events;

use App\Domain\Deployments\Models\Deployment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A deployment's status, step or commit changed. Carries only identifiers and
 * public fields; the page reloads the rest.
 */
final class DeploymentUpdated implements ShouldBroadcastNow
{
    public function __construct(public readonly Deployment $deployment) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('deployments.'.$this->deployment->id);
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
            'id' => $this->deployment->id,
            'status' => $this->deployment->status->value,
            'current_step' => $this->deployment->current_step,
        ];
    }
}
