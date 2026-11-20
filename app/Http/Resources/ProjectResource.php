<?php

namespace App\Http\Resources;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectHook;
use App\Domain\Projects\Support\EnvFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the browser may know about a project. The deploy private key, the
 * webhook secret and .env values are never included.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'repository_url' => $project->repository_url,
            'branch' => $project->branch,
            'deploy_path' => $project->deploy_path,
            'server' => [
                'id' => $project->server->id,
                'name' => $project->server->name,
                'host' => $project->server->host,
            ],
            'initialized_at' => $project->initialized_at?->toIso8601String(),
        ];
    }

    /**
     * Detail and settings views.
     *
     * @return array<string, mixed>
     */
    public function withSettings(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;

        return [
            ...$this->toArray($request),
            'deploy_public_key' => $project->deploy_public_key,
            'shared_paths' => $project->shared_paths,
            'run_composer' => $project->run_composer,
            'run_npm_build' => $project->run_npm_build,
            'run_migrations' => $project->run_migrations,
            'queue_restart' => $project->queue_restart,
            'php_fpm_service' => $project->php_fpm_service,
            'keep_releases' => $project->keep_releases,
            'health_check_url' => $project->health_check_url,
            'health_check_status' => $project->health_check_status,
            'auto_rollback' => $project->auto_rollback,
            'hooks' => $project->hooks->map(fn (ProjectHook $hook): array => [
                'stage' => $hook->stage->value,
                'command' => $hook->command,
            ])->all(),
            'environment' => [
                'keys' => EnvFile::parse($project->env_content)->keys(),
                'synced_at' => $project->env_synced_at?->toIso8601String(),
            ],
        ];
    }
}
