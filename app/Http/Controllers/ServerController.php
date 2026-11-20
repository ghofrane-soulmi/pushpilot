<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Actions\CreateServer;
use App\Domain\Servers\Actions\DeleteServer;
use App\Domain\Servers\Actions\UpdateServer;
use App\Domain\Servers\Exceptions\ServerInUseException;
use App\Domain\Servers\Models\Server;
use App\Http\Requests\Servers\ServerRequest;
use App\Http\Resources\ServerResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Server::class);

        $servers = Server::query()->orderBy('name')->get();

        return Inertia::render('servers/Index', [
            'servers' => ServerResource::collection($servers)->toArray($request),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Server::class);

        return Inertia::render('servers/Create');
    }

    public function store(ServerRequest $request, CreateServer $createServer): RedirectResponse
    {
        Gate::authorize('create', Server::class);

        $server = $createServer->handle($request->serverAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server created. Authorize its key, then test the connection.')]);

        return to_route('servers.show', $server);
    }

    public function show(Request $request, Server $server): Response
    {
        Gate::authorize('view', $server);

        return Inertia::render('servers/Show', [
            'server' => (new ServerResource($server))->withDetails($request),
            'projects' => $server->projects()->orderBy('name')->get(['id', 'name', 'deploy_path'])
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'deploy_path' => $project->deploy_path,
                ])->all(),
        ]);
    }

    public function edit(Request $request, Server $server): Response
    {
        Gate::authorize('update', $server);

        return Inertia::render('servers/Edit', [
            'server' => (new ServerResource($server))->toArray($request),
        ]);
    }

    public function update(ServerRequest $request, Server $server, UpdateServer $updateServer): RedirectResponse
    {
        Gate::authorize('update', $server);

        $updateServer->handle($server, $request->serverAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server updated.')]);

        return to_route('servers.show', $server);
    }

    public function destroy(Server $server, DeleteServer $deleteServer): RedirectResponse
    {
        Gate::authorize('delete', $server);

        try {
            $deleteServer->handle($server);
        } catch (ServerInUseException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return to_route('servers.show', $server);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server deleted.')]);

        return to_route('servers.index');
    }
}
