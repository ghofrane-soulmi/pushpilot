<?php

use App\Domain\Projects\Support\EnvFile;

$sample = <<<'ENV'
# Application
APP_NAME=Demo
APP_KEY=base64:abc==

export DB_PASSWORD="s3cr3t pass"
not a variable line
APP_NAME=Duplicate
ENV;

it('lists variable names in order without values', function () use ($sample) {
    expect(EnvFile::parse($sample)->keys())->toBe(['APP_NAME', 'APP_KEY', 'DB_PASSWORD']);
});

it('handles empty content', function () {
    expect(EnvFile::parse(null)->keys())->toBe([])
        ->and(EnvFile::parse('')->toString())->toBe('');
});

it('replaces an existing variable in place and keeps comments', function () use ($sample) {
    $result = EnvFile::parse($sample)->set('APP_NAME', 'Pushpilot')->toString();

    expect($result)->toBe("# Application\nAPP_NAME=Pushpilot\nAPP_KEY=base64:abc==\n\nexport DB_PASSWORD=\"s3cr3t pass\"\nnot a variable line\n");
});

it('appends new variables', function () {
    expect(EnvFile::parse("APP_NAME=Demo\n")->set('APP_ENV', 'production')->toString())
        ->toBe("APP_NAME=Demo\nAPP_ENV=production\n");
});

it('removes every occurrence of a variable', function () use ($sample) {
    $file = EnvFile::parse($sample)->remove('APP_NAME');

    expect($file->keys())->toBe(['APP_KEY', 'DB_PASSWORD'])
        ->and($file->toString())->not->toContain('APP_NAME');
});

it('quotes values so they read back literally', function (string $value, string $line) {
    expect(EnvFile::parse('')->set('X', $value)->toString())->toBe("X={$line}\n");
})->with([
    'plain' => ['production', 'production'],
    'url' => ['https://example.com/path', 'https://example.com/path'],
    'empty' => ['', ''],
    'spaces' => ['hello world', "'hello world'"],
    'dollar' => ['pa$word', "'pa\$word'"],
    'hash' => ['a#b', "'a#b'"],
    'single quote' => ["it's", '"it\'s"'],
    'single quote and dollar' => ["it's \$HOME", '"it\'s \\$HOME"'],
]);

it('exposes raw values for output masking', function () use ($sample) {
    expect(EnvFile::parse($sample)->values())->toContain('Demo', 'base64:abc==', 's3cr3t pass');
});

it('rejects invalid names and multi-line values', function (string $key, string $value) {
    EnvFile::parse('')->set($key, $value);
})->with([
    ['1ABC', 'x'],
    ['APP-NAME', 'x'],
    ['APP NAME', 'x'],
    ['APP_KEY', "line1\nINJECTED=1"],
])->throws(InvalidArgumentException::class);
