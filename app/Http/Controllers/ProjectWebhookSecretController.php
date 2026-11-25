<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Actions\GenerateWebhookSecret;
use App\Domain\Projects\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Generates a webhook secret and flashes it for a single page view.
 */
class ProjectWebhookSecretController extends Controller
{
    public function __invoke(Project $project, GenerateWebhookSecret $generate): RedirectResponse
    {
        Gate::authorize('update', $project);

        Inertia::flash('webhookSecret', $generate->handle($project));
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('New webhook secret generated. Copy it now: it will not be shown again.'),
        ]);

        return to_route('projects.show', $project);
    }
}
