<?php

namespace App\Http\Requests\Servers;

use App\Domain\Servers\Rules\Host;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by create and update. Authorization happens in the controller
 * through ServerPolicy.
 */
class ServerRequest extends FormRequest
{
    /**
     * Linux user names: lowercase letter or underscore first, max 32 chars.
     */
    public const USERNAME_PATTERN = '/^[a-z_][a-z0-9_-]{0,31}$/';

    protected function prepareForValidation(): void
    {
        $this->merge([
            'host' => is_string($this->input('host')) ? strtolower(trim($this->input('host'))) : $this->input('host'),
            'port' => $this->input('port') ?? 22,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'host' => ['required', 'string', 'max:253', new Host],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'regex:'.self::USERNAME_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'The SSH user must be a valid Linux user name (lowercase letters, digits, "-" and "_").',
        ];
    }

    /**
     * @return array{name: string, host: string, port: int, username: string}
     */
    public function serverAttributes(): array
    {
        /** @var array{name: string, host: string, port: int|string, username: string} $data */
        $data = $this->safe()->only(['name', 'host', 'port', 'username']);

        return [...$data, 'port' => (int) $data['port']];
    }
}
