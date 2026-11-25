<?php

namespace App\Domain\Deployments\Pipeline\Steps;

use App\Domain\Deployments\Pipeline\DeploymentContext;
use App\Support\Ssh\Shell;

/**
 * Step 2: shallow-clone the branch into the release with the project's deploy
 * key, then record the commit (DEP-6).
 */
final class CloneRepository extends Step
{
    public function name(): string
    {
        return 'clone';
    }

    public function label(): string
    {
        return 'Clone repository';
    }

    public function run(DeploymentContext $context): void
    {
        $project = $context->project;

        $context->run(
            $project->gitSshCommand()
            .' git clone --depth 1 --single-branch --branch '.Shell::quote($project->branch)
            .' -- '.Shell::quote($project->repository_url).' '.$context->quotedReleasePath(),
            'Could not clone the repository. Check that the deploy key is added to it and that the branch exists',
        );

        $commit = $context->capture('git -C '.$context->quotedReleasePath().' log -1 --format=%H%n%an%n%s');

        if ($commit->successful()) {
            [$hash, $author, $message] = array_pad(explode("\n", trim($commit->output), 3), 3, '');

            $deployment = $context->deployment;
            $deployment->commit_hash = substr($hash, 0, 40) ?: null;
            $deployment->commit_author = mb_substr($author, 0, 255) ?: null;
            $deployment->commit_message = $message !== '' ? $message : null;
            $deployment->save();

            $context->logger->info('Commit '.substr($hash, 0, 7).' by '.$author.': '.$message);
        }
    }
}
