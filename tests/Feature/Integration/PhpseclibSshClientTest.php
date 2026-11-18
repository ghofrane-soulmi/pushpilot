<?php

use App\Domain\Servers\Support\SshKeyPair;
use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshConnectionException;
use App\Support\Ssh\Exceptions\SshTimeoutException;
use App\Support\Ssh\OutputStream;
use App\Support\Ssh\PhpseclibSshClient;
use App\Support\Ssh\Shell;
use App\Support\Ssh\SshCredentials;
use Illuminate\Support\Facades\Process;

/*
 * Real SSH against the `target` container (docker compose up -d target).
 *
 *   SSH_INTEGRATION=1 php artisan test --group=integration
 *
 * Host/port default to the published port on the host (localhost:2222).
 * Skipped unless SSH_INTEGRATION=1, so the normal suite stays offline.
 */

pest()->group('integration');

beforeEach(function () {
    if (! env('SSH_INTEGRATION')) {
        $this->markTestSkipped('Set SSH_INTEGRATION=1 with the target container running.');
    }

    $this->host = (string) env('SSH_INTEGRATION_HOST', '127.0.0.1');
    $this->port = (int) env('SSH_INTEGRATION_PORT', 2222);

    $this->keys = SshKeyPair::generate('pushpilot-integration-test');

    // Authorize the fresh key for `deployer` inside the target container.
    Process::run([
        'docker', 'compose', 'exec', '-T', 'target', 'sh', '-c',
        'echo '.Shell::quote($this->keys->publicKey).' >> /home/deployer/.ssh/authorized_keys',
    ])->throw();
});

afterEach(function () {
    if (! isset($this->keys)) {
        return;
    }

    // Remove only the line holding this test's key.
    $keyBody = explode(' ', $this->keys->publicKey)[1];

    Process::run([
        'docker', 'compose', 'exec', '-T', 'target', 'sh', '-c',
        'grep -vF '.Shell::quote($keyBody).' /home/deployer/.ssh/authorized_keys > /tmp/ak; cat /tmp/ak > /home/deployer/.ssh/authorized_keys; rm -f /tmp/ak',
    ]);
});

function targetCredentials(object $test, string $username = 'deployer', ?string $privateKey = null): SshCredentials
{
    return new SshCredentials($test->host, $test->port, $username, $privateKey ?? $test->keys->privateKey);
}

it('logs in with the generated key and runs commands', function () {
    $ssh = new PhpseclibSshClient;
    $ssh->connect(targetCredentials($this));

    $result = $ssh->run('whoami');

    expect($ssh->isConnected())->toBeTrue()
        ->and($result->successful())->toBeTrue()
        ->and(trim($result->output))->toBe('deployer');

    $ssh->disconnect();
    expect($ssh->isConnected())->toBeFalse();
});

it('streams stdout, separates stderr and reports exit codes', function () {
    $ssh = new PhpseclibSshClient;
    $ssh->connect(targetCredentials($this));

    $chunks = [];
    $result = $ssh->run('echo out; echo err >&2; exit 3', function (OutputStream $stream, string $chunk) use (&$chunks) {
        $chunks[] = [$stream, trim($chunk)];
    });

    expect($result->exitCode)->toBe(3)
        ->and(trim($result->output))->toBe('out')
        ->and(trim($result->errorOutput))->toBe('err')
        ->and($chunks)->toContain([OutputStream::Stdout, 'out'], [OutputStream::Stderr, 'err']);
});

it('passes quoted values through the remote shell untouched', function () {
    $ssh = new PhpseclibSshClient;
    $ssh->connect(targetCredentials($this));

    $hostile = "it's \$(id); `id` && echo pwned";

    expect($ssh->run('printf %s '.Shell::quote($hostile))->output)->toBe($hostile);
});

it('allows only the php-fpm reload through sudo', function () {
    $ssh = new PhpseclibSshClient;
    $ssh->connect(targetCredentials($this));

    expect($ssh->run('sudo -n systemctl reload php8.3-fpm')->successful())->toBeTrue()
        ->and($ssh->run('sudo -n systemctl restart nginx')->failed())->toBeTrue()
        ->and($ssh->run('sudo -n id')->failed())->toBeTrue();
});

it('reports a rejected key as an authentication failure', function () {
    $ssh = new PhpseclibSshClient;

    expect(fn () => $ssh->connect(targetCredentials($this, privateKey: SshKeyPair::generate()->privateKey)))
        ->toThrow(SshAuthenticationException::class, 'Authentication failed');
});

it('reports an unknown user as an authentication failure', function () {
    expect(fn () => (new PhpseclibSshClient)->connect(targetCredentials($this, username: 'nobody')))
        ->toThrow(SshAuthenticationException::class);
});

it('reports a closed port as a connection failure within the timeout', function () {
    $started = microtime(true);

    expect(fn () => (new PhpseclibSshClient(connectTimeoutSeconds: 5))
        ->connect(new SshCredentials($this->host, 1, 'deployer', $this->keys->privateKey)))
        ->toThrow(SshConnectionException::class);

    expect(microtime(true) - $started)->toBeLessThan(10);
});

it('stops commands that exceed their timeout', function () {
    $ssh = new PhpseclibSshClient;
    $ssh->connect(targetCredentials($this));

    expect(fn () => $ssh->run('sleep 5', timeoutSeconds: 1))->toThrow(SshTimeoutException::class);
    expect($ssh->isConnected())->toBeFalse();
});
