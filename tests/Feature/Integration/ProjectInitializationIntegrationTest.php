<?php

use App\Domain\Projects\Actions\InitializeProject;
use App\Domain\Projects\Actions\SaveEnvironment;
use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Models\Server;
use App\Domain\Servers\Support\SshKeyPair;
use App\Support\Ssh\PhpseclibSshClient;
use App\Support\Ssh\Shell;
use App\Support\Ssh\SshClient;
use Illuminate\Support\Facades\Process;

/*
 * Initialize and .env upload against the real `target` container.
 *
 *   SSH_INTEGRATION=1 php artisan test --group=integration
 */

pest()->group('integration');

function targetExec(string $command): string
{
    return Process::run(['docker', 'compose', 'exec', '-T', 'target', 'sh', '-c', $command])->throw()->output();
}

beforeEach(function () {
    if (! env('SSH_INTEGRATION')) {
        $this->markTestSkipped('Set SSH_INTEGRATION=1 with the target container running.');
    }

    $this->app->bind(SshClient::class, fn () => new PhpseclibSshClient);

    $keys = SshKeyPair::generate('pushpilot-integration-test');
    $this->serverPublicKey = $keys->publicKey;
    targetExec('echo '.Shell::quote($keys->publicKey).' >> /home/deployer/.ssh/authorized_keys');

    $server = Server::factory()->create([
        'host' => (string) env('SSH_INTEGRATION_HOST', '127.0.0.1'),
        'port' => (int) env('SSH_INTEGRATION_PORT', 2222),
        'username' => 'deployer',
        'private_key' => $keys->privateKey,
        'public_key' => $keys->publicKey,
    ]);

    $this->project = Project::factory()->for($server)->create([
        'slug' => 'integration-app',
        'deploy_path' => '/var/www/app/integration-app',
        'repository_url' => 'git@github.com:pushpilot-invalid/does-not-exist.git',
        'shared_paths' => ['.env', 'storage/', 'public/uploads/'],
    ]);
});

afterEach(function () {
    if (! isset($this->serverPublicKey)) {
        return;
    }

    $keyBody = explode(' ', $this->serverPublicKey)[1];
    targetExec('rm -rf /var/www/app/integration-app /home/deployer/.ssh/pushpilot_integration-app'
        .' && grep -vF '.Shell::quote($keyBody).' /home/deployer/.ssh/authorized_keys > /tmp/ak;'
        .' cat /tmp/ak > /home/deployer/.ssh/authorized_keys; rm -f /tmp/ak');
});

it('creates releases/, shared/ and the shared paths with the right owner', function () {
    $result = app(InitializeProject::class)->handle($this->project);

    // The repository is deliberately unreachable from the container: structure succeeds, repo check fails.
    expect(collect($result->steps)->take(3)->pluck('ok')->all())->toBe([true, true, true])
        ->and($result->successful)->toBeFalse()
        ->and($this->project->refresh()->initialized_at)->not->toBeNull();

    $listing = targetExec('cd /var/www/app/integration-app && find . -maxdepth 4 | sort && stat -c "%U %a %n" shared/.env');

    expect($listing)
        ->toContain('./releases')
        ->toContain('./shared/public/uploads')
        ->toContain('./shared/storage/framework/views')
        ->toContain('./shared/storage/logs')
        ->toContain('deployer');
});

it('installs the deploy key readable only by the deploy user', function () {
    app(InitializeProject::class)->handle($this->project);

    expect(trim(targetExec('stat -c "%U %a" /home/deployer/.ssh/pushpilot_integration-app')))->toBe('deployer 600')
        ->and(targetExec('head -1 /home/deployer/.ssh/pushpilot_integration-app'))->toContain('BEGIN OPENSSH PRIVATE KEY');
});

it('writes the .env byte-for-byte with mode 640, even with hostile content', function () {
    app(InitializeProject::class)->handle($this->project->refresh());

    $value = "it's \$(touch /tmp/pwned) `id` \"quoted\" #hash";
    $result = app(SaveEnvironment::class)->set($this->project->refresh(), 'TRICKY', $value);

    expect($result->successful)->toBeTrue();

    $remote = targetExec('cat /var/www/app/integration-app/shared/.env');
    $mode = trim(targetExec('stat -c "%a" /var/www/app/integration-app/shared/.env'));
    $pwned = trim(targetExec('test -e /tmp/pwned && echo yes || echo no'));

    expect($remote)->toBe($this->project->refresh()->env_content)
        ->and($mode)->toBe('640')
        ->and($pwned)->toBe('no');
});

it('is safe to run twice', function () {
    app(InitializeProject::class)->handle($this->project);
    $second = app(InitializeProject::class)->handle($this->project->refresh());

    expect(collect($second->steps)->take(3)->pluck('ok')->all())->toBe([true, true, true]);
});
