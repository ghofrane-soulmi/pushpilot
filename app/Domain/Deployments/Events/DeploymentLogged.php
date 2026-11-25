<?php

namespace App\Domain\Deployments\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * DEP-5: new log lines for a deployment, already masked. Broadcast
 * immediately (not queued) so the browser sees them within a second.
 */
final class DeploymentLogged implements ShouldBroadcastNow
{
    /**
     * @param  list<array{step: string, line: string, stream: string}>  $lines
     */
    public function __construct(
        public readonly int $deploymentId,
        public readonly array $lines,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('deployments.'.$this->deploymentId);
    }

    public function broadcastAs(): string
    {
        return 'log';
    }

    /**
     * @return array{lines: list<array{step: string, line: string, stream: string}>}
     */
    public function broadcastWith(): array
    {
        return ['lines' => $this->lines];
    }
}
