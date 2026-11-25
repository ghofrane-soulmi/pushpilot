<?php

namespace App\Domain\Deployments\Pipeline;

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Exceptions\StepFailedException;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Deployments\Pipeline\Steps\ActivateRelease;
use App\Domain\Deployments\Pipeline\Steps\BuildAssets;
use App\Domain\Deployments\Pipeline\Steps\CleanupReleases;
use App\Domain\Deployments\Pipeline\Steps\CloneRepository;
use App\Domain\Deployments\Pipeline\Steps\InstallComposerDependencies;
use App\Domain\Deployments\Pipeline\Steps\LinkSharedPaths;
use App\Domain\Deployments\Pipeline\Steps\OptimizeApplication;
use App\Domain\Deployments\Pipeline\Steps\PrepareRelease;
use App\Domain\Deployments\Pipeline\Steps\ReloadServices;
use App\Domain\Deployments\Pipeline\Steps\RunHooks;
use App\Domain\Deployments\Pipeline\Steps\RunMigrations;
use App\Domain\Projects\Enums\HookStage;
use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshException;
use App\Support\Ssh\SshClient;
use Throwable;

/**
 * Runs a deployment's steps in order (spec 8.2) and guarantees that `current`
 * only ever moves to a release whose every step before activation succeeded.
 */
final class Pipeline
{
    private bool $activated = false;

    private ?DeploymentContext $context = null;

    public function __construct(private readonly SshClient $ssh) {}

    /**
     * @return list<DeploymentStep>
     */
    public static function steps(): array
    {
        return [
            new PrepareRelease,
            new CloneRepository,
            new LinkSharedPaths,
            new RunHooks(HookStage::BeforeBuild),
            new InstallComposerDependencies,
            new BuildAssets,
            new RunHooks(HookStage::AfterBuild),
            new RunHooks(HookStage::BeforeActivate),
            new RunMigrations,
            new OptimizeApplication,
            new ActivateRelease,
            new ReloadServices,
            new RunHooks(HookStage::AfterActivate),
            new CleanupReleases,
        ];
    }

    public function run(Deployment $deployment): void
    {
        $project = $deployment->project()->with(['server', 'hooks'])->firstOrFail();
        $logger = new DeploymentLogger($deployment, SecretMasker::forProject($project));
        $stepTimeout = (int) config('pushpilot.deployments.step_timeout');
        $deadline = microtime(true) + (int) config('pushpilot.deployments.global_timeout');

        $deployment->status = DeploymentStatus::Running;
        $deployment->started_at = now()->toImmutable();
        $deployment->release_name = now('UTC')->format('YmdHis');
        $deployment->save();

        $this->activated = false;
        $this->context = new DeploymentContext($deployment, $project, $this->ssh, $logger, $deployment->release_name, $stepTimeout);

        try {
            if ($project->initialized_at === null) {
                $this->finish(DeploymentStatus::Failed, 'The project is not initialized. Run Initialize on the project page first.');

                return;
            }

            try {
                $this->ssh->connect($project->server->credentials());
            } catch (SshAuthenticationException) {
                $this->finish(DeploymentStatus::Failed, "Could not log in to {$project->server->name}: the server rejected Pushpilot's key.");

                return;
            } catch (SshException $e) {
                $this->finish(DeploymentStatus::Failed, "Could not connect to {$project->server->name}: {$e->getMessage()}");

                return;
            }

            foreach (self::steps() as $step) {
                if (! $this->runStep($step, $deadline, $stepTimeout)) {
                    return;
                }
            }

            $this->finish(DeploymentStatus::Succeeded);
        } catch (Throwable $e) {
            report($e);
            $this->abort(DeploymentStatus::Failed, 'Unexpected error: '.$e->getMessage());
        } finally {
            $this->ssh->disconnect();
            $this->context = null;
        }
    }

    /**
     * @return bool whether the pipeline should continue
     */
    private function runStep(DeploymentStep $step, float $deadline, int $stepTimeout): bool
    {
        $context = $this->context();
        $deployment = $context->deployment;

        if (! $step->shouldRun($context)) {
            return true;
        }

        $deployment->refresh();

        if ($deployment->cancel_requested_at !== null && ! $this->activated) {
            $this->abort(DeploymentStatus::Cancelled, 'Cancelled before "'.$step->label().'".');

            return false;
        }

        $remaining = (int) floor($deadline - microtime(true));

        if ($remaining <= 0) {
            $this->abort(DeploymentStatus::Failed, 'The deployment exceeded its global timeout.');

            return false;
        }

        $context->setTimeout(min($stepTimeout, $remaining));
        $deployment->current_step = $step->name();
        $deployment->save();

        $context->logger->forStep($step->name());
        $context->logger->info('▶ '.$step->label());

        try {
            $step->run($context);
        } catch (StepFailedException|SshException $e) {
            return $this->handleFailure($step, $e->getMessage());
        }

        if ($step instanceof ActivateRelease) {
            $this->activated = true;
        }

        return true;
    }

    private function handleFailure(DeploymentStep $step, string $message): bool
    {
        $context = $this->context();
        $context->logger->info('✗ '.$message);

        $policy = $this->activated ? FailurePolicy::Warn : $step->onFailure();

        if ($policy === FailurePolicy::Warn) {
            $context->deployment->addWarning($step->label().': '.$message);
            $context->deployment->save();

            return true;
        }

        if ($policy === FailurePolicy::AbortWithDatabaseWarning) {
            $context->deployment->addWarning('Migrations failed part-way: the database may already have changed. The live release was not switched.');
        }

        $this->abort(DeploymentStatus::Failed, $step->label().' failed: '.$message);

        return false;
    }

    /**
     * Stop and, if the release never went live, delete its directory.
     */
    private function abort(DeploymentStatus $status, string $message): void
    {
        if (! $this->activated) {
            $this->removeRelease();
        }

        $this->finish($status, $message);
    }

    private function removeRelease(): void
    {
        $context = $this->context();

        try {
            if (! $this->ssh->isConnected()) {
                $this->ssh->connect($context->project->server->credentials());
            }

            $context->logger->forStep('cleanup');
            $context->capture('rm -rf -- '.$context->quotedReleasePath());
            $context->logger->info('Removed the unfinished release '.$context->releaseName.'. The live release was not changed.');
        } catch (SshException) {
            $context->logger->info('Could not remove the unfinished release '.$context->releaseName.'; it is not live and will be cleaned up later.');
        }
    }

    private function finish(DeploymentStatus $status, ?string $error = null): void
    {
        $context = $this->context();
        $deployment = $context->deployment;

        $context->logger->forStep('pipeline');

        if ($error !== null) {
            $context->logger->info($error);
        }

        $deployment->status = $status;
        $deployment->error_message = $error;
        $deployment->current_step = null;
        $deployment->finished_at = now()->toImmutable();
        $deployment->save();
    }

    private function context(): DeploymentContext
    {
        return $this->context ?? throw new \LogicException('The pipeline is not running.');
    }
}
