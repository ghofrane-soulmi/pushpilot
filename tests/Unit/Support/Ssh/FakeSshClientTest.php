<?php

use App\Support\Ssh\CommandResult;
use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshException;
use App\Support\Ssh\FakeSshClient;
use App\Support\Ssh\OutputStream;
use App\Support\Ssh\SshCredentials;
use PHPUnit\Framework\AssertionFailedError;

function credentials(string $host = '203.0.113.10'): SshCredentials
{
    return new SshCredentials($host, 22, 'deployer', 'PRIVATE-KEY-MATERIAL');
}

it('records connections and commands', function () {
    $ssh = new FakeSshClient;

    $ssh->connect(credentials());
    $ssh->run('whoami');
    $ssh->run('php -v');

    expect($ssh->isConnected())->toBeTrue()
        ->and($ssh->commands())->toBe(['whoami', 'php -v']);

    $ssh->assertConnectedTo('203.0.113.10', 'deployer')
        ->assertRan('whoami')
        ->assertRanMatching('/^php /')
        ->assertNotRan('rm -rf /')
        ->assertRanInOrder(['whoami', 'php -v']);
});

it('succeeds with empty output for unscripted commands', function () {
    $ssh = new FakeSshClient;
    $ssh->connect(credentials());

    $result = $ssh->run('true');

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe('');
});

it('returns scripted responses, latest registration first', function () {
    $ssh = (new FakeSshClient)
        ->respondTo('whoami', 'deployer')
        ->respondToPattern('/^git /', CommandResult::failure(128, 'fatal: not a git repository'))
        ->respondToPattern('/^git --version$/', 'git version 2.43.0');

    $ssh->connect(credentials());

    expect($ssh->run('whoami')->output)->toBe('deployer')
        ->and($ssh->run('git --version')->output)->toBe('git version 2.43.0')
        ->and($ssh->run('git status')->exitCode)->toBe(128)
        ->and($ssh->run('git status')->failed())->toBeTrue();
});

it('streams stdout and stderr to the output callback', function () {
    $ssh = (new FakeSshClient)->respondTo('composer install', new CommandResult(1, 'Installing...', 'Your lock file is out of date'));
    $ssh->connect(credentials());

    $received = [];
    $ssh->run('composer install', function (OutputStream $stream, string $chunk) use (&$received) {
        $received[] = [$stream, $chunk];
    });

    expect($received)->toBe([
        [OutputStream::Stdout, 'Installing...'],
        [OutputStream::Stderr, 'Your lock file is out of date'],
    ]);
});

it('can simulate a rejected key', function () {
    $ssh = (new FakeSshClient)->failConnectionWith(new SshAuthenticationException('Authentication failed'));

    expect(fn () => $ssh->connect(credentials()))->toThrow(SshAuthenticationException::class, 'Authentication failed')
        ->and($ssh->isConnected())->toBeFalse();

    $ssh->assertConnectedTo('203.0.113.10')->assertNothingRan();
});

it('refuses to run commands without a session', function () {
    $ssh = new FakeSshClient;

    expect(fn () => $ssh->run('whoami'))->toThrow(SshException::class);

    $ssh->connect(credentials());
    $ssh->disconnect();

    expect(fn () => $ssh->run('whoami'))->toThrow(SshException::class);
});

it('fails assertions with a helpful message', function () {
    $ssh = new FakeSshClient;
    $ssh->connect(credentials());
    $ssh->run('whoami');

    expect(fn () => $ssh->assertRan('php -v'))
        ->toThrow(AssertionFailedError::class, 'Command [php -v] was not run');
});

it('never exposes the private key when dumped', function () {
    $dump = print_r(credentials(), true);

    expect($dump)->not->toContain('PRIVATE-KEY-MATERIAL')
        ->and($dump)->toContain('[redacted]')
        ->and(credentials()->privateKey())->toBe('PRIVATE-KEY-MATERIAL')
        ->and(credentials()->destination())->toBe('deployer@203.0.113.10:22');
});
