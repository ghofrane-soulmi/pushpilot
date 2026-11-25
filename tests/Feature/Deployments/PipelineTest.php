<?php

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Deployments\Pipeline\Pipeline;
use App\Domain\Deployments\Pipeline\SecretMasker;
use App\Domain\Projects\Enums\HookStage;
use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Models\Server;
use App\Support\Ssh\CommandResult;
use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\FakeSshClient;
use Carbon\CarbonImmutable;

const RELEASE = '20261125100000';
const RELEASE_PATH = "'/var/www/shop/releases/20261125100000'";

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00:00', 'UTC'));

    $this->ssh = FakeSshClient::swap()
        ->respondTo('git -C '.RELEASE_PATH.' log -1 --format=%H%n%an%n%s', str_repeat('c', 40)."\nAda Lovelace\nFix the checkout page\n");

    $this->project = Project::factory()
        ->for(Server::factory()->create(['host' => '203.0.113.10']))
        ->initialized()
        ->withEnv("APP_KEY=base64:supersecretkey\nDB_PASSWORD=hunter2-long\n")
        ->create([
            'slug' => 'shop',
            'deploy_path' => '/var/www/shop',
            'repository_url' => 'git@github.com:acme/shop.git',
            'branch' => 'main',
            'php_fpm_service' => 'php8.3-fpm',
            'keep_releases' => 5,
        ]);

    $this->deployment = Deployment::factory()->for($this->project)->create();
});

function runPipeline(Deployment $deployment): Deployment
{
    app(Pipeline::class)->run($deployment);

    return $deployment->refresh();
}

/**
 * @return list<string>
 */
function loggedSteps(Deployment $deployment): array
{
    return $deployment->logs()->pluck('step')->unique()->values()->all();
}

it('runs every enabled step in order and records the commit', function () {
    $deployment = runPipeline($this->deployment);

    expect($deployment->status)->toBe(DeploymentStatus::Succeeded)
        ->and($deployment->release_name)->toBe(RELEASE)
        ->and($deployment->commit_hash)->toBe(str_repeat('c', 40))
        ->and($deployment->commit_author)->toBe('Ada Lovelace')
        ->and($deployment->commit_message)->toBe('Fix the checkout page')
        ->and($deployment->started_at)->not->toBeNull()
        ->and($deployment->finished_at)->not->toBeNull()
        ->and($deployment->current_step)->toBeNull()
        ->and($deployment->error_message)->toBeNull();

    $this->ssh->assertConnectedTo('203.0.113.10')->assertRanInOrder([
        "mkdir -p '/var/www/shop/releases' '/var/www/shop/shared' && test ! -e ".RELEASE_PATH,
        "GIT_SSH_COMMAND='ssh -i \"\$HOME/.ssh/pushpilot_shop\" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new'"
            ." git clone --depth 1 --single-branch --branch 'main' -- 'git@github.com:acme/shop.git' ".RELEASE_PATH,
        "rm -rf '/var/www/shop/releases/20261125100000/.env' && ln -nfs '/var/www/shop/shared/.env' '/var/www/shop/releases/20261125100000/.env'"
            ." && rm -rf '/var/www/shop/releases/20261125100000/storage' && ln -nfs '/var/www/shop/shared/storage' '/var/www/shop/releases/20261125100000/storage'",
        'cd '.RELEASE_PATH.' && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress',
        'cd '.RELEASE_PATH.' && php artisan optimize',
        "cd '/var/www/shop' && ln -nfs 'releases/20261125100000' current.tmp && mv -Tf current.tmp current",
        "sudo -n systemctl reload 'php8.3-fpm'",
    ]);

    $this->ssh->assertRanMatching("/^cd '\\/var\\/www\\/shop\\/releases' && ls -1 .* tail -n \\+6 /");

    expect(loggedSteps($deployment))->toBe(['prepare', 'clone', 'shared', 'composer', 'optimize', 'activate', 'reload', 'cleanup', 'pipeline']);
});

it('skips assets, migrations and queue restart unless enabled', function () {
    runPipeline($this->deployment);

    $this->ssh->assertNotRan('cd '.RELEASE_PATH.' && npm ci --no-audit --no-fund && npm run build')
        ->assertNotRan('cd '.RELEASE_PATH.' && php artisan migrate --force --no-interaction');

    expect(collect($this->ssh->commands())->contains(fn ($c) => str_contains($c, 'queue:restart')))->toBeFalse();
});

it('builds assets, migrates and restarts queues when enabled', function () {
    $this->project->update(['run_npm_build' => true, 'run_migrations' => true, 'queue_restart' => true]);

    runPipeline($this->deployment);

    $this->ssh->assertRanInOrder([
        'cd '.RELEASE_PATH.' && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress',
        'cd '.RELEASE_PATH.' && npm ci --no-audit --no-fund && npm run build',
        'cd '.RELEASE_PATH.' && php artisan migrate --force --no-interaction',
        'cd '.RELEASE_PATH.' && php artisan optimize',
        "cd '/var/www/shop' && ln -nfs 'releases/20261125100000' current.tmp && mv -Tf current.tmp current",
        "cd '/var/www/shop/current' && php artisan queue:restart",
    ]);
});

it('never activates a release whose build failed, and deletes it', function () {
    $this->ssh->respondToPattern('/composer install/', new CommandResult(1, '', 'Your requirements could not be resolved'));

    $deployment = runPipeline($this->deployment);

    expect($deployment->status)->toBe(DeploymentStatus::Failed)
        ->and($deployment->error_message)->toContain('Install Composer dependencies failed');

    $this->ssh->assertRan('rm -rf -- '.RELEASE_PATH);
    expect(collect($this->ssh->commands())->contains(fn ($c) => str_contains($c, 'mv -Tf current.tmp current')))->toBeFalse()
        ->and($deployment->logs()->where('line', 'like', '%could not be resolved%')->exists())->toBeTrue();
});

it('warns that the database may have changed when migrations fail', function () {
    $this->project->update(['run_migrations' => true]);
    $this->ssh->respondToPattern('/artisan migrate/', CommandResult::failure(1, 'SQLSTATE[42S01]'));

    $deployment = runPipeline($this->deployment);

    expect($deployment->status)->toBe(DeploymentStatus::Failed)
        ->and($deployment->warnings)->toHaveCount(1)
        ->and($deployment->warnings[0])->toContain('database may already have changed');

    $this->ssh->assertRan('rm -rf -- '.RELEASE_PATH);
});

it('only warns when something fails after the release is live', function () {
    $this->ssh->respondTo("sudo -n systemctl reload 'php8.3-fpm'", CommandResult::failure(1, 'sudo: a password is required'));

    $deployment = runPipeline($this->deployment);

    expect($deployment->status)->toBe(DeploymentStatus::Succeeded)
        ->and($deployment->warnings[0])->toContain('Reload PHP-FPM')
        ->and($deployment->warnings[0])->toContain('sudoers');

    $this->ssh->assertNotRan('rm -rf -- '.RELEASE_PATH);
});

it('runs hooks at their stage, in order, from the right directory', function () {
    $this->project->hooks()->createMany([
        ['stage' => HookStage::BeforeActivate, 'command' => 'php artisan storage:link', 'order' => 0],
        ['stage' => HookStage::AfterBuild, 'command' => 'echo built', 'order' => 1],
        ['stage' => HookStage::AfterActivate, 'command' => 'php artisan horizon:terminate', 'order' => 2],
    ]);

    runPipeline($this->deployment);

    $this->ssh->assertRanInOrder([
        'cd '.RELEASE_PATH.' && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress',
        'cd '.RELEASE_PATH.' && echo built',
        'cd '.RELEASE_PATH.' && php artisan storage:link',
        "cd '/var/www/shop' && ln -nfs 'releases/20261125100000' current.tmp && mv -Tf current.tmp current",
        "cd '/var/www/shop/current' && php artisan horizon:terminate",
    ]);
});

it('fails before activation when a hook fails', function () {
    $this->project->hooks()->create(['stage' => HookStage::BeforeActivate, 'command' => 'exit 3', 'order' => 0]);
    $this->ssh->respondTo('cd '.RELEASE_PATH.' && exit 3', CommandResult::failure(3));

    $deployment = runPipeline($this->deployment);

    expect($deployment->status)->toBe(DeploymentStatus::Failed)
        ->and($deployment->error_message)->toContain('Hook failed: exit 3');
});

it('masks secrets in the stored log', function () {
    $this->ssh->respondToPattern('/artisan optimize/', "Caching config... APP_KEY=base64:supersecretkey\npassword: hunter2-long\n");

    $deployment = runPipeline($this->deployment);
    $log = $deployment->logs()->pluck('line')->implode("\n");

    expect($log)->not->toContain('supersecretkey')
        ->not->toContain('hunter2-long')
        ->toContain(SecretMasker::MASK);
});

it('refuses to deploy a project that is not initialized', function () {
    $this->project->forceFill(['initialized_at' => null])->save();

    $deployment = runPipeline($this->deployment);

    expect($deployment->status)->toBe(DeploymentStatus::Failed)
        ->and($deployment->error_message)->toContain('not initialized');
    $this->ssh->assertNothingRan();
});

it('explains a rejected server key', function () {
    $this->ssh->failConnectionWith(new SshAuthenticationException('rejected'));

    $deployment = runPipeline($this->deployment);

    expect($deployment->status)->toBe(DeploymentStatus::Failed)
        ->and($deployment->error_message)->toContain('rejected Pushpilot');
});

it('stops before the next step when cancelled, and removes the release', function () {
    $deployment = $this->deployment;
    $deployment->cancel_requested_at = now()->toImmutable();
    $deployment->save();

    $deployment = runPipeline($deployment);

    expect($deployment->status)->toBe(DeploymentStatus::Cancelled);
    $this->ssh->assertRan('rm -rf -- '.RELEASE_PATH);
    expect(collect($this->ssh->commands())->contains(fn ($c) => str_contains($c, 'git clone')))->toBeFalse();
});

it('fails when the global timeout is exceeded', function () {
    config(['pushpilot.deployments.global_timeout' => 0]);

    $deployment = runPipeline($this->deployment);

    expect($deployment->status)->toBe(DeploymentStatus::Failed)
        ->and($deployment->error_message)->toContain('global timeout');
});

it('keeps only the newest releases and never the new one', function () {
    $this->project->update(['keep_releases' => 3]);

    runPipeline($this->deployment);

    $this->ssh->assertRanMatching('/tail -n \+4 \| grep -vxF \'20261125100000\'/');
});
