<?php

namespace App\Http\Requests\Projects;

use App\Domain\Projects\Rules\MatchesPattern;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['repository_url', 'branch', 'deploy_path'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if (is_string($this->input('deploy_path')) && $this->input('deploy_path') !== '/') {
            $this->merge(['deploy_path' => rtrim($this->input('deploy_path'), '/')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'server_id' => ['required', 'integer', Rule::exists('servers', 'id')],
            'repository_url' => ['required', 'string', 'max:255', MatchesPattern::repositoryUrl()],
            'branch' => ['required', 'string', 'max:255', MatchesPattern::branch()],
            'deploy_path' => [
                'required', 'string', 'max:255', MatchesPattern::deployPath(),
                Rule::unique('projects', 'deploy_path')->where('server_id', $this->integer('server_id')),
            ],
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
     * @return array{server_id: int, name: string, repository_url: string, branch: string, deploy_path: string}
     */
    public function projectAttributes(): array
    {
        /** @var array{name: string, repository_url: string, branch: string, deploy_path: string} $data */
        $data = $this->safe()->only(['name', 'repository_url', 'branch', 'deploy_path']);

        return [...$data, 'server_id' => $this->integer('server_id')];
    }
}
