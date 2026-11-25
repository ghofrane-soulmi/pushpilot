<?php

namespace App\Domain\Deployments\Pipeline;

use App\Domain\Deployments\Exceptions\StepFailedException;
use App\Support\Ssh\Exceptions\SshException;

/**
 * One stage of the deployment pipeline (spec 8.2). Steps are small, ordered
 * and individually testable with FakeSshClient.
 */
interface DeploymentStep
{
    /**
     * Machine name stored with each log line, e.g. "composer".
     */
    public function name(): string;

    /**
     * Label shown in the UI, e.g. "Install Composer dependencies".
     */
    public function label(): string;

    /**
     * Skip steps that do not apply (e.g. assets when npm build is off).
     */
    public function shouldRun(DeploymentContext $context): bool;

    public function onFailure(): FailurePolicy;

    /**
     * @throws StepFailedException|SshException
     */
    public function run(DeploymentContext $context): void;
}
