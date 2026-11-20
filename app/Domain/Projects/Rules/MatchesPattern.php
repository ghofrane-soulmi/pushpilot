<?php

namespace App\Domain\Projects\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule wrapping one ProjectPatterns check.
 */
final class MatchesPattern implements ValidationRule
{
    /**
     * @param  Closure(string): bool  $check
     */
    private function __construct(
        private readonly Closure $check,
        private readonly string $message,
    ) {}

    public static function repositoryUrl(): self
    {
        return new self(
            ProjectPatterns::isRepositoryUrl(...),
            'The :attribute must be an SSH Git URL, for example git@github.com:owner/repo.git.',
        );
    }

    public static function branch(): self
    {
        return new self(ProjectPatterns::isBranch(...), 'The :attribute is not a valid Git branch name.');
    }

    public static function deployPath(): self
    {
        return new self(
            ProjectPatterns::isDeployPath(...),
            'The :attribute must be an absolute path such as /var/www/my-app (not a system directory).',
        );
    }

    public static function sharedPath(): self
    {
        return new self(
            ProjectPatterns::isSharedPath(...),
            'Each shared path must be relative to the app, for example .env or storage/.',
        );
    }

    public static function phpFpmService(): self
    {
        return new self(ProjectPatterns::isPhpFpmService(...), 'The :attribute must look like php8.3-fpm.');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ($this->check)($value)) {
            $fail($this->message);
        }
    }
}
