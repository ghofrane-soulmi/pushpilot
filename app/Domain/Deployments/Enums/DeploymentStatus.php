<?php

namespace App\Domain\Deployments\Enums;

/**
 * DEP-7.
 */
enum DeploymentStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RolledBack = 'rolled_back';

    public function isFinished(): bool
    {
        return match ($this) {
            self::Queued, self::Running => false,
            self::Succeeded, self::Failed, self::Cancelled, self::RolledBack => true,
        };
    }
}
