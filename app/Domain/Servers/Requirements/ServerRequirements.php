<?php

namespace App\Domain\Servers\Requirements;

use App\Support\Ssh\CommandResult;

/**
 * The requirements check (SRV-4): which commands to run on a server and how to
 * turn their results into a report the UI shows as ✓ / ✗ with fix hints.
 *
 * Commands are constants: no user input is ever part of them.
 *
 * @phpstan-type Check array{key: string, label: string, ok: bool, required: bool, version: string|null, hint: string|null}
 * @phpstan-type Report array{checks: list<Check>, php_fpm_service: string|null, ready: bool}
 */
final class ServerRequirements
{
    public const MIN_PHP_VERSION = '8.2.0';

    /**
     * @var array<string, array{label: string, command: string, pattern: string, required: bool, hint: string}>
     */
    private const CHECKS = [
        'git' => [
            'label' => 'Git',
            'command' => 'git --version',
            'pattern' => '/git version (\d+\.\d+(?:\.\d+)?)/',
            'required' => true,
            'hint' => 'sudo apt install git',
        ],
        'php' => [
            'label' => 'PHP',
            'command' => "php -r 'echo PHP_VERSION;'",
            'pattern' => '/^(\d+\.\d+\.\d+)/',
            'required' => true,
            'hint' => 'PHP 8.2 or newer is required: sudo apt install php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip',
        ],
        'composer' => [
            'label' => 'Composer',
            'command' => 'composer --version --no-ansi --no-interaction 2>/dev/null',
            'pattern' => '/Composer (?:version )?(\d+\.\d+\.\d+)/',
            'required' => true,
            'hint' => 'Install Composer globally: https://getcomposer.org/download/',
        ],
        'node' => [
            'label' => 'Node.js',
            'command' => 'node --version',
            'pattern' => '/^v?(\d+\.\d+\.\d+)/',
            'required' => false,
            'hint' => 'Only needed to build assets: install Node 20+ from https://github.com/nodesource/distributions',
        ],
        'npm' => [
            'label' => 'npm',
            'command' => 'npm --version',
            'pattern' => '/^(\d+\.\d+\.\d+)/',
            'required' => false,
            'hint' => 'Only needed to build assets: it comes with Node.js',
        ],
        'php_fpm' => [
            'label' => 'PHP-FPM',
            'command' => 'ls -d /etc/php/*/fpm 2>/dev/null',
            'pattern' => '#/etc/php/(\d+\.\d+)/fpm#',
            'required' => true,
            'hint' => 'sudo apt install php8.3-fpm',
        ],
    ];

    /**
     * @return array<string, string> check key => command
     */
    public static function commands(): array
    {
        return array_map(fn (array $check): string => $check['command'], self::CHECKS);
    }

    /**
     * @param  array<string, CommandResult>  $results  keyed like commands()
     * @return Report
     */
    public static function evaluate(array $results): array
    {
        $phpVersion = self::versionFrom('php', $results['php'] ?? null);
        $fpmService = self::phpFpmService($results['php_fpm'] ?? null, $phpVersion);

        $checks = [];

        foreach (self::CHECKS as $key => $check) {
            $version = $key === 'php_fpm'
                ? $fpmService
                : self::versionFrom($key, $results[$key] ?? null);

            $ok = $version !== null
                && ($key !== 'php' || version_compare($version, self::MIN_PHP_VERSION, '>='));

            $checks[] = [
                'key' => $key,
                'label' => $check['label'],
                'ok' => $ok,
                'required' => $check['required'],
                'version' => $version,
                'hint' => $ok ? null : $check['hint'],
            ];
        }

        $ready = collect($checks)->every(fn (array $check): bool => $check['ok'] || ! $check['required']);

        return ['checks' => $checks, 'php_fpm_service' => $fpmService, 'ready' => $ready];
    }

    private static function versionFrom(string $key, ?CommandResult $result): ?string
    {
        if ($result === null || $result->failed()) {
            return null;
        }

        return preg_match(self::CHECKS[$key]['pattern'], trim($result->output), $match) === 1 ? $match[1] : null;
    }

    /**
     * Pick the FPM service matching the CLI PHP version, else the newest one.
     */
    private static function phpFpmService(?CommandResult $result, ?string $phpVersion): ?string
    {
        if ($result === null || $result->failed()) {
            return null;
        }

        preg_match_all(self::CHECKS['php_fpm']['pattern'], $result->output, $matches);
        $versions = $matches[1];

        if ($versions === []) {
            return null;
        }

        usort($versions, fn (string $a, string $b): int => version_compare($b, $a));
        $chosen = $versions[0];

        if ($phpVersion !== null) {
            $cliMinor = implode('.', array_slice(explode('.', $phpVersion), 0, 2));

            if (in_array($cliMinor, $versions, true)) {
                $chosen = $cliMinor;
            }
        }

        return "php{$chosen}-fpm";
    }
}
