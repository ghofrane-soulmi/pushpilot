<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Models\Server;
use App\Domain\Servers\Support\SshKeyPair;
use Illuminate\Support\Str;

/**
 * PRJ-1 / PRJ-2: store the project, generate its deploy key and webhook secret.
 */
final class CreateProject
{
    /**
     * @param  array{server_id: int, name: string, repository_url: string, branch: string, deploy_path: string}  $attributes
     */
    public function handle(array $attributes): Project
    {
        $server = Server::query()->findOrFail($attributes['server_id']);
        $slug = $this->uniqueSlug($attributes['name']);
        $keys = SshKeyPair::generate('pushpilot-deploy@'.$slug);

        $project = new Project([
            'name' => $attributes['name'],
            'repository_url' => $attributes['repository_url'],
            'branch' => $attributes['branch'],
            'deploy_path' => rtrim($attributes['deploy_path'], '/'),
            'shared_paths' => Project::DEFAULT_SHARED_PATHS,
            'php_fpm_service' => $this->detectedPhpFpmService($server),
        ]);

        $project->server()->associate($server);
        $project->slug = $slug;
        $project->deploy_private_key = $keys->privateKey;
        $project->deploy_public_key = $keys->publicKey;
        $project->webhook_secret = Str::random(40);
        $project->save();

        return $project;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $base = substr($base, 0, 100);
        $slug = $base;
        $suffix = 2;

        while (Project::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Pre-fill PRJ-7 from the server's last requirements check (SRV-4).
     */
    private function detectedPhpFpmService(Server $server): ?string
    {
        $service = $server->requirements['php_fpm_service'] ?? null;

        return is_string($service) ? $service : null;
    }
}
