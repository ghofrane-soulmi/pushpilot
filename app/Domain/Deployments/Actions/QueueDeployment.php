<?php

namespace App\Domain\Deployments\Actions;

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Enums\DeploymentTrigger;
use App\Domain\Deployments\Enums\DeploymentType;
use App\Domain\Deployments\Exceptions\ProjectNotReadyException;
use App\Domain\Deployments\Jobs\DeployJob;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Projects\Models\Project;
use App\Models\User;

/**
 * DEP-1 / DEP-4: record a queued deployment and hand it to the worker.
 */
final class QueueDeployment
{
    public function handle(Project $project, ?User $user, DeploymentTrigger $trigger = DeploymentTrigger::Manual): Deployment
    {
        if ($project->initialized_at === null) {
            throw new ProjectNotReadyException('Initialize the project before deploying it.');
        }

        $deployment = new Deployment;
        $deployment->project()->associate($project);
        $deployment->user()->associate($user);
        $deployment->type = DeploymentType::Deploy;
        $deployment->trigger = $trigger;
        $deployment->status = DeploymentStatus::Queued;
        $deployment->save();

        DeployJob::dispatch($deployment);

        return $deployment;
    }
}
