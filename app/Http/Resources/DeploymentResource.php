<?php

namespace App\Http\Resources;

use App\Domain\Deployments\Models\Deployment;
use App\Domain\Deployments\Models\DeploymentLog;
use App\Domain\Deployments\Pipeline\DeploymentStep;
use App\Domain\Deployments\Pipeline\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deployment
 */
class DeploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Deployment $deployment */
        $deployment = $this->resource;

        return [
            'id' => $deployment->id,
            'type' => $deployment->type->value,
            'trigger' => $deployment->trigger->value,
            'status' => $deployment->status->value,
            'commit_hash' => $deployment->commit_hash,
            'commit_message' => $deployment->commit_message,
            'commit_author' => $deployment->commit_author,
            'release_name' => $deployment->release_name,
            'current_step' => $deployment->current_step,
            'user' => $deployment->user?->name,
            'created_at' => $deployment->created_at?->toIso8601String(),
            'started_at' => $deployment->started_at?->toIso8601String(),
            'finished_at' => $deployment->finished_at?->toIso8601String(),
            'duration' => $deployment->durationInSeconds(),
        ];
    }

    /**
     * Detail page: steps, warnings, error and the (already masked) log.
     *
     * @return array<string, mixed>
     */
    public function withLog(Request $request): array
    {
        /** @var Deployment $deployment */
        $deployment = $this->resource;
        $logs = $deployment->logs()->get(['id', 'step', 'line', 'stream']);
        $loggedSteps = $logs->pluck('step')->unique()->all();

        return [
            ...$this->toArray($request),
            'error_message' => $deployment->error_message,
            'warnings' => $deployment->warnings ?? [],
            'cancel_requested' => $deployment->cancel_requested_at !== null,
            'project' => [
                'id' => $deployment->project->id,
                'name' => $deployment->project->name,
                'branch' => $deployment->project->branch,
            ],
            'steps' => array_values(array_map(fn (DeploymentStep $step): array => [
                'name' => $step->name(),
                'label' => $step->label(),
                'ran' => in_array($step->name(), $loggedSteps, true),
            ], Pipeline::steps())),
            'log' => $logs->map(fn (DeploymentLog $log): array => [
                'id' => $log->id,
                'step' => $log->step,
                'line' => $log->line,
                'stream' => $log->stream->value,
            ])->all(),
        ];
    }
}
