<?php

namespace Database\Factories;

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
{
    protected $model = Deployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => 'deploy',
            'trigger' => 'manual',
            'status' => DeploymentStatus::Queued,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => DeploymentStatus::Running,
            'started_at' => now(),
            'release_name' => now()->format('YmdHis'),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => DeploymentStatus::Succeeded,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'release_name' => now()->subMinute()->format('YmdHis'),
            'commit_hash' => str_repeat('a', 40),
            'commit_message' => 'Initial commit',
            'commit_author' => 'Ada',
        ]);
    }
}
