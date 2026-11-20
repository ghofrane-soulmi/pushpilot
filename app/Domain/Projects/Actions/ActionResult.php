<?php

namespace App\Domain\Projects\Actions;

/**
 * Outcome of a remote operation, as shown to the user. Never carries secrets.
 */
final readonly class ActionResult
{
    /**
     * @param  list<array{label: string, ok: bool, message: string|null}>  $steps
     */
    public function __construct(
        public bool $successful,
        public string $message,
        public array $steps = [],
    ) {}

    /**
     * @return array{successful: bool, message: string, steps: list<array{label: string, ok: bool, message: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'message' => $this->message,
            'steps' => $this->steps,
        ];
    }
}
