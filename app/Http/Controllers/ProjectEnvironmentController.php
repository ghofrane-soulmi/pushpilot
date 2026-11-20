<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Actions\ActionResult;
use App\Domain\Projects\Actions\SaveEnvironment;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\EnvFile;
use App\Http\Requests\Projects\EnvironmentRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Write-only .env editor (PRJ-4). Values go in, only names come back out.
 */
class ProjectEnvironmentController extends Controller
{
    public function edit(Request $request, Project $project): Response
    {
        Gate::authorize('manageEnvironment', $project);

        $project->load(['server', 'hooks']);

        return Inertia::render('projects/Environment', [
            'project' => (new ProjectResource($project))->withSettings($request),
        ]);
    }

    public function set(EnvironmentRequest $request, Project $project, SaveEnvironment $saveEnvironment): RedirectResponse
    {
        Gate::authorize('manageEnvironment', $project);

        $key = $request->string('key')->toString();
        $existed = EnvFile::parse($project->env_content)->has($key);

        $result = $saveEnvironment->set($project, $key, $request->string('value')->toString());

        return $this->respond($project, $result, $existed ? "{$key} updated." : "{$key} added.");
    }

    public function remove(Project $project, string $key, SaveEnvironment $saveEnvironment): RedirectResponse
    {
        Gate::authorize('manageEnvironment', $project);

        abort_unless(preg_match(EnvFile::KEY_PATTERN, $key) === 1, 404);

        return $this->respond($project, $saveEnvironment->remove($project, $key), "{$key} removed.");
    }

    public function replace(EnvironmentRequest $request, Project $project, SaveEnvironment $saveEnvironment): RedirectResponse
    {
        Gate::authorize('manageEnvironment', $project);

        $result = $saveEnvironment->replace($project, $request->string('content')->toString());

        return $this->respond($project, $result, '.env replaced.');
    }

    private function respond(Project $project, ActionResult $result, string $saved): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => $result->successful ? 'success' : 'warning',
            'message' => $saved.' '.$result->message,
        ]);

        return to_route('projects.environment.edit', $project);
    }
}
