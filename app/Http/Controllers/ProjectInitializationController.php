<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Actions\InitializeProject;
use App\Domain\Projects\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * PRJ-10: create releases/ and shared/ on the server and install the deploy key.
 */
class ProjectInitializationController extends Controller
{
    public function __invoke(Project $project, InitializeProject $initializeProject): RedirectResponse
    {
        Gate::authorize('initialize', $project);

        $result = $initializeProject->handle($project);

        Inertia::flash('initialization', $result->toArray());
        Inertia::flash('toast', [
            'type' => $result->successful ? 'success' : 'error',
            'message' => $result->message,
        ]);

        return back();
    }
}
