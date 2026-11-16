<?php

namespace App\Support\Ssh;

final readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $output = '',
        public string $errorOutput = '',
    ) {}

    public static function success(string $output = ''): self
    {
        return new self(0, $output);
    }

    public static function failure(int $exitCode = 1, string $errorOutput = ''): self
    {
        return new self($exitCode, '', $errorOutput);
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }
}
