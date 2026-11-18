<?php

namespace App\Domain\Servers\Actions;

final readonly class ConnectionTestResult
{
    public function __construct(
        public bool $successful,
        public string $message,
    ) {}

    /**
     * @return array{successful: bool, message: string}
     */
    public function toArray(): array
    {
        return ['successful' => $this->successful, 'message' => $this->message];
    }
}
