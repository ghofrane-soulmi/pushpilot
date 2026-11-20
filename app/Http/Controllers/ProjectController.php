<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Enums\HookStage;
use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Models\Server;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $projects = Project::query()->with('server')->orderBy('name')->get();

        return Inertia::render('projects/Index', [
            'projects' => ProjectResource::collection($projects)->toArray($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('projects/Create', [
            'servers' => Server::query()->orderBy('name')->get(['id', 'name', 'host'])
                ->map(fn (Server $server): array => ['id' => $server->id, 'name' => $server->name, 'host' => $server->host])
                ->all(),
            'selectedServerId' => $request->integer('server') ?: null,
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $project = $createProject->handle($request->projectAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created. Add its deploy key to the repository, then initialize it.')]);

        return to_route('projects.show', $project);
    }

    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load(['server', 'hooks']);

        return Inertia::render('projects/Show', [
            'project' => (new ProjectResource($project))->withSettings($request),
        ]);
    }

    public function edit(Request $request, Project $project): Response
    {
        Gate::authorize('update', $project);

        $project->load(['server', 'hooks']);

        return Inertia::render('projects/Settings', [
            'project' => (new ProjectResource($project))->withSettings($request),
            'hookStages' => array_map(
                fn (HookStage $stage): array => ['value' => $stage->value, 'label' => $stage->label()],
                HookStage::cases(),
            ),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $updateProject): RedirectResponse
    {
        Gate::authorize('update', $project);

        $pathChanged = $request->input('deploy_path') !== $project->deploy_path;

        $updateProject->handle($project, $request->settings(), $request->hooks());

        Inertia::flash('toast', [
            'type' => $pathChanged ? 'warning' : 'success',
            'message' => $pathChanged
                ? __('Settings saved. The deploy path changed: initialize the project again.')
                : __('Settings saved.'),
        ]);

        return to_route('projects.edit', $project);
    }

    public function destroy(Project $project, DeleteProject $deleteProject): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $deleteProject->handle($project);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted. Files on the server were not touched.')]);

        return to_route('projects.index');
    }
}
