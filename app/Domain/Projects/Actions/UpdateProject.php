<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Enums\HookStage;
use App\Domain\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Project settings: general, build, release and health-check options, plus
 * custom hooks (PRJ-3, PRJ-5..PRJ-9). The deploy key and secrets never change here.
 */
final class UpdateProject
{
    /**
     * @param  array<string, mixed>  $settings  validated project attributes
     * @param  list<array{stage: string, command: string}>  $hooks
     */
    public function handle(Project $project, array $settings, array $hooks): Project
    {
        return DB::transaction(function () use ($project, $settings, $hooks): Project {
            if (isset($settings['deploy_path']) && is_string($settings['deploy_path'])) {
                $settings['deploy_path'] = rtrim($settings['deploy_path'], '/');
            }

            $project->fill($settings);

            // A new location on the server must be initialized again.
            if ($project->isDirty('deploy_path')) {
                $project->initialized_at = null;
                $project->env_synced_at = null;
            }

            $project->save();

            $project->hooks()->delete();

            foreach ($hooks as $order => $hook) {
                $project->hooks()->create([
                    'stage' => HookStage::from($hook['stage']),
                    'command' => $hook['command'],
                    'order' => $order,
                ]);
            }

            return $project;
        });
    }
}
