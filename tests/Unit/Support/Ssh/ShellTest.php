<?php

use App\Support\Ssh\Shell;

it('single-quotes values for a POSIX shell regardless of the host OS', function (string $value, string $quoted) {
    expect(Shell::quote($value))->toBe($quoted);
})->with([
    'plain' => ['main', "'main'"],
    'empty' => ['', "''"],
    'spaces' => ['my app', "'my app'"],
    'command separator' => ['x; rm -rf /', "'x; rm -rf /'"],
    'substitution' => ['$(id) `id`', "'$(id) `id`'"],
    'double quotes and percent' => ['"%PATH%"', "'\"%PATH%\"'"],
    'single quote' => ["it's", "'it'\\''s'"],
    'newline' => ["a\nb", "'a\nb'"],
]);

it('round-trips through a real POSIX shell', function (string $value) {
    $output = shell_exec('sh -c '.Shell::quote('printf %s '.Shell::quote($value)));

    expect($output)->toBe($value);
})->with(['plain', "it's", '$(id); `id` && echo pwned', 'a "b" \\c'])
    ->skip(PHP_OS_FAMILY === 'Windows', 'Needs a POSIX shell (runs in Docker and CI).');
