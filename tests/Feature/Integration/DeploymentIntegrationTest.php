<?php

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Deployments\Pipeline\Pipeline;
use App\Domain\Projects\Actions\InitializeProject;
use App\Domain\Projects\Enums\HookStage;
use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Models\Server;
use App\Domain\Servers\Support\SshKeyPair;
use App\Support\Ssh\PhpseclibSshClient;
use App\Support\Ssh\Shell;
use App\Support\Ssh\SshClient;
use Illuminate\Support\Facades\Process;

/*
 * Real deployments of tests/Fixtures/sample-app to the `target` container.
 * The repository is a bare Git repo inside the container, cloned over SSH
 * with the project's deploy key, exactly like GitHub would be.
 *
 *   SSH_INTEGRATION=1 php artisan test --group=integration
 */

pest()->group('integration');

const DEPLOY_PATH = '/var/www/app/pushpilot-it';

function onTarget(string $command, string $user = 'deployer'): string
{
    return Process::timeout(120)
        ->run(['docker', 'compose', 'exec', '-T', '-u', $user, 'target', 'sh', '-c', $command])
        ->throw()
        ->output();
}

function deployNow(Project $project): Deployment
{
    $deployment = Deployment::factory()->for($project)->create();
    app(Pipeline::class)->run($deployment);

    return $deployment->refresh();
}

beforeEach(function () {
    if (! env('SSH_INTEGRATION')) {
        $this->markTestSkipped('Set SSH_INTEGRATION=1 with the target container running.');
    }

    $this->app->bind(SshClient::class, fn () => new PhpseclibSshClient);

    // A bare repository holding the sample app, owned by deployer.
    Process::run(['docker', 'compose', 'cp', base_path('tests/Fixtures/sample-app'), 'target:/tmp/pushpilot-sample'])->throw();
    onTarget('chown -R deployer:deployer /tmp/pushpilot-sample', 'root');
    onTarget('rm -rf ~/repos/sample.git /tmp/sample-work && mkdir -p ~/repos'
        .' && git init -q --bare -b main ~/repos/sample.git'
        .' && cp -r /tmp/pushpilot-sample /tmp/sample-work && cd /tmp/sample-work'
        .' && git init -q -b main && git add -A'
        .' && git -c user.name=Ada -c user.email=ada@example.com commit -q -m "First release"'
        .' && git push -q ~/repos/sample.git main');

    $serverKeys = SshKeyPair::generate('pushpilot-it-server');
    $this->project = Project::factory()
        ->for(Server::factory()->create([
            'host' => (string) env('SSH_INTEGRATION_HOST', '127.0.0.1'),
            'port' => (int) env('SSH_INTEGRATION_PORT', 2222),
            'username' => 'deployer',
            'private_key' => $serverKeys->privateKey,
            'public_key' => $serverKeys->publicKey,
        ]))
        ->withEnv("APP_NAME=PushpilotSample\nAPP_KEY=base64:integration-secret-value\n")
        ->create([
            'slug' => 'pushpilot-it',
            'deploy_path' => DEPLOY_PATH,
            'repository_url' => 'ssh://deployer@localhost/home/deployer/repos/sample.git',
            'branch' => 'main',
            'php_fpm_service' => 'php8.3-fpm',
            'queue_restart' => true,
            'keep_releases' => 2,
        ]);

    // Pushpilot's server key logs in; the project's deploy key reads the repo.
    $this->authorized = [$serverKeys->publicKey, $this->project->deploy_public_key];
    foreach ($this->authorized as $key) {
        onTarget('echo '.Shell::quote($key).' >> ~/.ssh/authorized_keys');
    }

    $initialized = app(InitializeProject::class)->handle($this->project);
    expect($initialized->successful)->toBeTrue($initialized->message);
    $this->project->refresh();
});

afterEach(function () {
    if (! isset($this->authorized)) {
        return;
    }

    $filter = implode(' ', array_map(fn (string $key) => '-e '.Shell::quote(explode(' ', $key)[1]), $this->authorized));
    onTarget('rm -rf '.DEPLOY_PATH.' ~/.ssh/pushpilot_pushpilot-it ~/repos /tmp/sample-work'
        .' && grep -vF '.$filter.' ~/.ssh/authorized_keys > /tmp/ak; cat /tmp/ak > ~/.ssh/authorized_keys; rm -f /tmp/ak');
});

it('deploys the app, links shared files and switches current atomically', function () {
    $deployment = deployNow($this->project);

    expect($deployment->status)->toBe(DeploymentStatus::Succeeded, (string) $deployment->error_message)
        ->and($deployment->commit_message)->toBe('First release')
        ->and($deployment->commit_author)->toBe('Ada');

    $release = $deployment->release_name;

    expect(trim(onTarget('readlink '.DEPLOY_PATH.'/current')))->toBe("releases/{$release}")
        ->and(onTarget('php '.DEPLOY_PATH.'/current/public/index.php'))->toBe("release={$release}\napp_name=PushpilotSample\n")
        ->and(trim(onTarget('readlink '.DEPLOY_PATH."/releases/{$release}/storage")))->toBe(DEPLOY_PATH.'/shared/storage')
        ->and(onTarget('test -f '.DEPLOY_PATH."/releases/{$release}/vendor/autoload.php && echo ok"))->toContain('ok');

    $log = $deployment->logs()->pluck('line')->implode("\n");

    expect($log)->toContain('sample-app artisan: optimize')
        ->toContain('Reloaded php8.3-fpm')
        ->toContain("Release {$release} is live")
        ->not->toContain('integration-secret-value');
});

it('switches to the new release on a second deploy and cleans up old ones', function () {
    $first = deployNow($this->project);
    $this->travel(2)->seconds();
    $second = deployNow($this->project);
    $this->travel(2)->seconds();
    $third = deployNow($this->project);

    expect($third->status)->toBe(DeploymentStatus::Succeeded, (string) $third->error_message)
        ->and(trim(onTarget('readlink '.DEPLOY_PATH.'/current')))->toBe("releases/{$third->release_name}")
        ->and(onTarget('ls -1 '.DEPLOY_PATH.'/releases'))->toBe("{$second->release_name}\n{$third->release_name}\n")
        ->and($first->release_name)->not->toBe($third->release_name);
});

it('leaves the live release untouched when a deployment fails', function () {
    $good = deployNow($this->project);

    $this->project->hooks()->create(['stage' => HookStage::BeforeActivate, 'command' => 'echo broken >&2; exit 4', 'order' => 0]);
    $this->travel(2)->seconds();
    $bad = deployNow($this->project->refresh());

    expect($bad->status)->toBe(DeploymentStatus::Failed)
        ->and($bad->error_message)->toContain('exit 4')
        ->and(trim(onTarget('readlink '.DEPLOY_PATH.'/current')))->toBe("releases/{$good->release_name}")
        ->and(trim(onTarget('ls -1 '.DEPLOY_PATH.'/releases')))->toBe($good->release_name)
        ->and($bad->logs()->where('stream', 'stderr')->where('line', 'broken')->exists())->toBeTrue();
});
