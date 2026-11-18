<?php

use App\Domain\Servers\Requirements\ServerRequirements;
use App\Support\Ssh\CommandResult;

/**
 * @param  array<string, CommandResult|string>  $overrides
 * @return array<string, CommandResult>
 */
function requirementResults(array $overrides = []): array
{
    $outputs = [
        'git' => 'git version 2.43.0',
        'php' => '8.3.6',
        'composer' => 'Composer version 2.8.4 2024-12-11 11:57:47',
        'node' => 'v20.18.1',
        'npm' => '10.8.2',
        'php_fpm' => "/etc/php/8.3/fpm\n",
        ...$overrides,
    ];

    return array_map(
        fn (CommandResult|string $output): CommandResult => $output instanceof CommandResult ? $output : CommandResult::success($output),
        $outputs,
    );
}

function requirementCheck(array $report, string $key): array
{
    return collect($report['checks'])->firstWhere('key', $key);
}

it('defines a fixed command per check', function () {
    expect(array_keys(ServerRequirements::commands()))
        ->toBe(['git', 'php', 'composer', 'node', 'npm', 'php_fpm']);
});

it('is ready when every required tool is present', function () {
    $report = ServerRequirements::evaluate(requirementResults());

    expect($report['ready'])->toBeTrue()
        ->and($report['php_fpm_service'])->toBe('php8.3-fpm')
        ->and(collect($report['checks'])->every(fn ($c) => $c['ok'] && $c['hint'] === null))->toBeTrue();
});

it('stays ready when only optional node/npm are missing', function () {
    $report = ServerRequirements::evaluate(requirementResults([
        'node' => CommandResult::failure(127),
        'npm' => CommandResult::failure(127),
    ]));

    expect($report['ready'])->toBeTrue()
        ->and(requirementCheck($report, 'node')['ok'])->toBeFalse()
        ->and(requirementCheck($report, 'node')['hint'])->toContain('Node');
});

it('is not ready when a required tool is missing', function (string $key) {
    $report = ServerRequirements::evaluate(requirementResults([$key => CommandResult::failure(127)]));

    expect($report['ready'])->toBeFalse()
        ->and(requirementCheck($report, $key)['ok'])->toBeFalse()
        ->and(requirementCheck($report, $key)['version'])->toBeNull()
        ->and(requirementCheck($report, $key)['hint'])->not->toBeNull();
})->with(['git', 'php', 'composer', 'php_fpm']);

it('rejects PHP older than 8.2', function () {
    $report = ServerRequirements::evaluate(requirementResults(['php' => '8.1.27']));

    expect(requirementCheck($report, 'php')['ok'])->toBeFalse()
        ->and(requirementCheck($report, 'php')['version'])->toBe('8.1.27')
        ->and($report['ready'])->toBeFalse();
});

it('treats unrecognised output as missing', function () {
    $report = ServerRequirements::evaluate(requirementResults(['git' => 'bash: git: command not found']));

    expect(requirementCheck($report, 'git')['ok'])->toBeFalse();
});

it('picks the PHP-FPM service matching the CLI version', function () {
    $report = ServerRequirements::evaluate(requirementResults([
        'php' => '8.2.20',
        'php_fpm' => "/etc/php/8.2/fpm\n/etc/php/8.3/fpm\n",
    ]));

    expect($report['php_fpm_service'])->toBe('php8.2-fpm');
});

it('falls back to the newest PHP-FPM when none matches the CLI', function () {
    $report = ServerRequirements::evaluate(requirementResults([
        'php' => '8.4.1',
        'php_fpm' => "/etc/php/8.2/fpm\n/etc/php/8.3/fpm\n",
    ]));

    expect($report['php_fpm_service'])->toBe('php8.3-fpm');
});
