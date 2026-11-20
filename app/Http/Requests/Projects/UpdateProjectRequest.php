<?php

namespace App\Http\Requests\Projects;

use App\Domain\Projects\Enums\HookStage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Rules\MatchesPattern;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Project settings: general, shared paths, build, hooks, releases, health check.
 */
class UpdateProjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $sharedPaths = $this->input('shared_paths');

        if (is_array($sharedPaths)) {
            $this->merge(['shared_paths' => array_values(array_filter(
                array_map(fn ($path) => is_string($path) ? trim($path) : $path, $sharedPaths),
                fn ($path) => $path !== '' && $path !== null,
            ))]);
        }

        if (is_string($this->input('deploy_path')) && $this->input('deploy_path') !== '/') {
            $this->merge(['deploy_path' => rtrim(trim($this->input('deploy_path')), '/')]);
        }

        $hooks = $this->input('hooks');
        if (is_array($hooks)) {
            $this->merge(['hooks' => array_values(array_filter(
                $hooks,
                fn ($hook) => ! is_array($hook) || trim((string) ($hook['command'] ?? '')) !== '',
            ))]);
        }

        foreach (['php_fpm_service', 'health_check_url'] as $nullable) {
            if ($this->input($nullable) === '') {
                $this->merge([$nullable => null]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:100'],
            'repository_url' => ['required', 'string', 'max:255', MatchesPattern::repositoryUrl()],
            'branch' => ['required', 'string', 'max:255', MatchesPattern::branch()],
            'deploy_path' => [
                'required', 'string', 'max:255', MatchesPattern::deployPath(),
                Rule::unique('projects', 'deploy_path')
                    ->where('server_id', $project->server_id)
                    ->ignore($project->id),
            ],
            'shared_paths' => ['present', 'array', 'max:20'],
            'shared_paths.*' => ['required', 'string', 'distinct', MatchesPattern::sharedPath()],
            'run_composer' => ['required', 'boolean'],
            'run_npm_build' => ['required', 'boolean'],
            'run_migrations' => ['required', 'boolean'],
            'queue_restart' => ['required', 'boolean'],
            'php_fpm_service' => ['nullable', 'string', 'max:50', MatchesPattern::phpFpmService()],
            'keep_releases' => ['required', 'integer', 'between:1,50'],
            'health_check_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'health_check_status' => ['required', 'integer', 'between:100,599'],
            'auto_rollback' => ['required', 'boolean'],
            'hooks' => ['present', 'array', 'max:40'],
            'hooks.*.stage' => ['required', Rule::enum(HookStage::class)],
            'hooks.*.command' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'deploy_path.unique' => 'Another project already deploys to this path on this server.',
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('auto_rollback') && $this->input('health_check_url') === null) {
                    $validator->errors()->add('auto_rollback', 'Automatic rollback needs a health-check URL.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->safe()->except(['hooks']);
    }

    /**
     * @return list<array{stage: string, command: string}>
     */
    public function hooks(): array
    {
        /** @var list<array{stage: string, command: string}> $hooks */
        $hooks = array_values(array_map(
            fn (array $hook): array => ['stage' => (string) $hook['stage'], 'command' => trim((string) $hook['command'])],
            $this->validated('hooks', []),
        ));

        return $hooks;
    }
}
