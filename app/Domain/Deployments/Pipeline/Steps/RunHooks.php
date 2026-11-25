<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;
use App\Domain\Deployments\Pipeline\FailurePolicy;
use App\Domain\Projects\Enums\HookStage;
use App\Domain\Projects\Models\ProjectHook;

/**
 * PRJ-6: the owner's own commands for one stage, run in the release directory.
 * Hooks are the owner's shell code on their own server and run as written.
 */
final class RunHooks extends Step
{
    public function __construct(private readonly HookStage $stage) {}

    public function name(): string
    {
        return 'hooks:'.$this->stage->value;
    }

    public function label(): string
    {
        return 'Hooks: '.strtolower($this->stage->label());
    }

    public function shouldRun(DeploymentContext $context): bool
    {
        return $this->hooks($context) !== [];
    }

    /**
     * After activation the release is live: a failing hook only warns.
     */
    public function onFailure(): FailurePolicy
    {
        return $this->stage === HookStage::AfterActivate
            ? FailurePolicy::Warn
            : FailurePolicy::AbortAndRemoveRelease;
    }

    public function run(DeploymentContext $context): void
    {
        $directory = $this->stage === HookStage::AfterActivate
            ? $context->quoted('current')
            : $context->quotedReleasePath();

        foreach ($this->hooks($context) as $hook) {
            $context->logger->info('$ '.$hook->command);
            $context->run("cd {$directory} && {$hook->command}", 'Hook failed: '.$hook->command);
        }
    }

    /**
     * @return list<ProjectHook>
     */
    private function hooks(DeploymentContext $context): array
    {
        return array_values($context->project->hooks
            ->filter(fn (ProjectHook $hook): bool => $hook->stage === $this->stage)
            ->sortBy('order')
            ->all());
    }
}
