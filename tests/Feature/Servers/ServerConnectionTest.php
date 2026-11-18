<?php

use App\Domain\Servers\Enums\ServerStatus;
use App\Domain\Servers\Models\Server;
use App\Models\User;
use App\Support\Ssh\CommandResult;
use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshConnectionException;
use App\Support\Ssh\FakeSshClient;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;

/**
 * Inertia flash data from the last response (e.g. "connectionTest.message").
 */
function flashed(TestResponse $response, string $key): mixed
{
    return data_get($response->baseRequest->session()->get(SessionKey::FLASH_DATA, []), $key);
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->server = Server::factory()->create(['host' => '203.0.113.10', 'username' => 'deployer']);
    $this->ssh = FakeSshClient::swap();
});

describe('SRV-3: test connection', function () {
    it('connects with the server key, runs whoami and marks the server reachable', function () {
        $this->ssh->respondTo('whoami', "deployer\n");

        $this->actingAs($this->owner)
            ->from(route('servers.show', $this->server))
            ->post(route('servers.test', $this->server))
            ->assertRedirect(route('servers.show', $this->server))
            ->assertInertiaFlash('connectionTest.successful', true)
            ->assertInertiaFlash('connectionTest.message', 'Connected as deployer.');

        $this->ssh->assertConnectedTo('203.0.113.10', 'deployer')->assertRan('whoami');

        expect($this->ssh->connections()[0]->privateKey())->toBe($this->server->private_key)
            ->and($this->ssh->isConnected())->toBeFalse();

        $this->server->refresh();

        expect($this->server->status)->toBe(ServerStatus::Reachable)
            ->and($this->server->last_checked_at)->not->toBeNull();
    });

    it('explains a rejected key', function () {
        $this->ssh->failConnectionWith(new SshAuthenticationException('rejected'));

        $response = $this->actingAs($this->owner)
            ->post(route('servers.test', $this->server))
            ->assertInertiaFlash('connectionTest.successful', false);

        expect(flashed($response, 'connectionTest.message'))
            ->toStartWith('Authentication failed')
            ->toContain('authorized_keys')
            ->and($this->server->refresh()->status)->toBe(ServerStatus::Unreachable);
    });

    it('reports an unreachable host', function () {
        $this->ssh->failConnectionWith(new SshConnectionException('Could not connect to 203.0.113.10:22: Connection refused'));

        $this->actingAs($this->owner)
            ->post(route('servers.test', $this->server))
            ->assertInertiaFlash('connectionTest.successful', false)
            ->assertInertiaFlash('connectionTest.message', 'Could not connect to 203.0.113.10:22: Connection refused');

        expect($this->server->refresh()->status)->toBe(ServerStatus::Unreachable);
    });

    it('reports a failing whoami', function () {
        $this->ssh->respondTo('whoami', CommandResult::failure(127));

        $this->actingAs($this->owner)
            ->post(route('servers.test', $this->server))
            ->assertInertiaFlash('connectionTest.successful', false);
    });
});

describe('SRV-4: requirements check', function () {
    beforeEach(function () {
        $this->ssh
            ->respondTo('git --version', 'git version 2.43.0')
            ->respondTo("php -r 'echo PHP_VERSION;'", '8.3.6')
            ->respondTo('composer --version --no-ansi --no-interaction 2>/dev/null', 'Composer version 2.8.4 2024-12-11 11:57:47')
            ->respondTo('node --version', 'v20.18.1')
            ->respondTo('npm --version', '10.8.2')
            ->respondTo('ls -d /etc/php/*/fpm 2>/dev/null', "/etc/php/8.3/fpm\n");
    });

    it('stores a ready report when everything is installed', function () {
        $this->actingAs($this->owner)
            ->post(route('servers.check', $this->server))
            ->assertInertiaFlash('toast.type', 'success');

        $this->server->refresh();
        $report = $this->server->requirements;

        expect($report['ready'])->toBeTrue()
            ->and($report['php_fpm_service'])->toBe('php8.3-fpm')
            ->and(collect($report['checks'])->pluck('version', 'key')->all())->toBe([
                'git' => '2.43.0',
                'php' => '8.3.6',
                'composer' => '2.8.4',
                'node' => '20.18.1',
                'npm' => '10.8.2',
                'php_fpm' => 'php8.3-fpm',
            ])
            ->and($this->server->status)->toBe(ServerStatus::Reachable);
    });

    it('runs only fixed commands', function () {
        $this->actingAs($this->owner)->post(route('servers.check', $this->server));

        expect($this->ssh->commands())->toBe([
            'git --version',
            "php -r 'echo PHP_VERSION;'",
            'composer --version --no-ansi --no-interaction 2>/dev/null',
            'node --version',
            'npm --version',
            'ls -d /etc/php/*/fpm 2>/dev/null',
        ]);
    });

    it('flags missing software with a fix hint', function () {
        $this->ssh
            ->respondTo('ls -d /etc/php/*/fpm 2>/dev/null', CommandResult::failure(2))
            ->respondTo('node --version', CommandResult::failure(127, 'node: command not found'));

        $this->actingAs($this->owner)
            ->post(route('servers.check', $this->server))
            ->assertInertiaFlash('toast.type', 'warning');

        $report = $this->server->refresh()->requirements;
        $checks = collect($report['checks'])->keyBy('key');

        expect($report['ready'])->toBeFalse()
            ->and($checks['php_fpm']['ok'])->toBeFalse()
            ->and($checks['php_fpm']['hint'])->toBe('sudo apt install php8.3-fpm')
            ->and($checks['node']['ok'])->toBeFalse()
            ->and($checks['node']['required'])->toBeFalse()
            ->and($checks['git']['hint'])->toBeNull();
    });

    it('marks the server unreachable when it cannot connect', function () {
        $this->ssh->failConnectionWith(new SshAuthenticationException('rejected'));

        $response = $this->actingAs($this->owner)
            ->post(route('servers.check', $this->server))
            ->assertInertiaFlash('toast.type', 'error');

        expect(flashed($response, 'connectionTest.message'))->toStartWith('Authentication failed')
            ->and($this->server->refresh()->status)->toBe(ServerStatus::Unreachable)
            ->and($this->server->requirements)->toBeNull();

        $this->ssh->assertNothingRan();
    });
});
