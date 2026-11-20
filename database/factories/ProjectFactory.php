<?php

namespace Database\Factories;

use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Models\Server;
use App\Domain\Servers\Support\SshKeyPair;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    private static ?SshKeyPair $keys = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$keys ??= SshKeyPair::generate();

        $slug = Str::slug(fake()->unique()->domainWord().' app');

        return [
            'server_id' => Server::factory(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'repository_url' => "git@github.com:acme/{$slug}.git",
            'branch' => 'main',
            'deploy_path' => "/var/www/{$slug}",
            'deploy_private_key' => self::$keys->privateKey,
            'deploy_public_key' => self::$keys->publicKey,
            'webhook_secret' => Str::random(40),
            'env_content' => null,
            'shared_paths' => Project::DEFAULT_SHARED_PATHS,
            'php_fpm_service' => 'php8.3-fpm',
        ];
    }

    public function initialized(): static
    {
        return $this->state(fn () => ['initialized_at' => now()]);
    }

    public function withEnv(string $content = "APP_NAME=Demo\nAPP_KEY=base64:secretvalue\nDB_PASSWORD=hunter2\n"): static
    {
        return $this->state(fn () => ['env_content' => $content]);
    }
}
