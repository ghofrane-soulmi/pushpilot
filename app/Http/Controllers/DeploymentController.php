<?php

namespace App\Http\Controllers;

use App\Domain\Deployments\Actions\CancelDeployment;
use App\Domain\Deployments\Actions\QueueDeployment;
use App\Domain\Deployments\Exceptions\ProjectNotReadyException;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Projects\Models\Project;
use App\Http\Resources\DeploymentResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeploymentController extends Controller
{
    /**
     * DEP-1: "Deploy now".
     */
    public function store(Project $project, QueueDeployment $queueDeployment, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('deploy', $project);

        try {
            $deployment = $queueDeployment->handle($project, $user);
        } catch (ProjectNotReadyException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return to_route('projects.show', $project);
        }

        return to_route('deployments.show', $deployment);
    }

    public function show(Request $request, Deployment $deployment): Response
    {
        Gate::authorize('view', $deployment);

        $deployment->load(['project', 'user']);

        return Inertia::render('deployments/Show', [
            'deployment' => (new DeploymentResource($deployment))->withLog($request),
        ]);
    }

    /**
     * DEP-8.
     */
    public function cancel(Deployment $deployment, CancelDeployment $cancelDeployment): RedirectResponse
    {
        Gate::authorize('cancel', $deployment);

        $cancelled = $cancelDeployment->handle($deployment);

        Inertia::flash('toast', $cancelled
            ? ['type' => 'info', 'message' => __('Cancelling. A running deployment stops before its next step.')]
            : ['type' => 'warning', 'message' => __('This deployment has already finished.')]);

        return to_route('deployments.show', $deployment);
    }
}
