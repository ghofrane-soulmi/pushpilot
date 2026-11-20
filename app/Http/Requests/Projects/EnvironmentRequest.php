<?php

namespace App\Http\Requests\Projects;

use App\Domain\Projects\Support\EnvFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Write-only .env editing (PRJ-4): set one variable, or replace the whole file.
 */
class EnvironmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->routeIs('projects.environment.replace')) {
            return [
                'content' => ['present', 'nullable', 'string', 'max:65535'],
            ];
        }

        return [
            'key' => ['required', 'string', 'max:255', 'regex:'.EnvFile::KEY_PATTERN],
            'value' => ['present', 'nullable', 'string', 'max:8192', 'not_regex:/[\r\n]/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'Variable names use letters, digits and underscores, and cannot start with a digit.',
            'value.not_regex' => 'Values cannot contain line breaks.',
        ];
    }
}
