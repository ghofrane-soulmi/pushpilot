<?php

use App\Domain\Projects\Rules\ProjectPatterns;

it('accepts SSH repository URLs only', function (string $url, bool $valid) {
    expect(ProjectPatterns::isRepositoryUrl($url))->toBe($valid);
})->with([
    ['git@github.com:acme/my-app.git', true],
    ['git@github.com:acme/my-app', true],
    ['git@gitlab.example.com:group/sub/app.git', true],
    ['ssh://git@github.com/acme/app.git', true],
    ['ssh://git@git.example.com:2222/acme/app.git', true],
    ['https://github.com/acme/app.git', false],
    ['git@github.com:acme/app.git; rm -rf /', false],
    ['git@github.com:acme/$(id).git', false],
    ['-oProxyCommand=sh@github.com:a/b', false],
    ['git@github.com:-u/app.git', false],
    ['git@github.com:../etc/passwd', false],
    ['file:///etc/passwd', false],
    ['git@github.com:acme/app .git', false],
]);

it('accepts valid git branch names only', function (string $branch, bool $valid) {
    expect(ProjectPatterns::isBranch($branch))->toBe($valid);
})->with([
    ['main', true],
    ['release/2026.11', true],
    ['feature/my-thing_2', true],
    ['-main', false],
    ['--upload-pack=evil', false],
    ['main;id', false],
    ['feature/../main', false],
    ['main.lock', false],
    ['feature/', false],
    ['/main', false],
    ['a//b', false],
    ['main ', false],
    ['$(id)', false],
    ['', false],
]);

it('accepts safe absolute deploy paths only', function (string $path, bool $valid) {
    expect(ProjectPatterns::isDeployPath($path))->toBe($valid);
})->with([
    ['/var/www/my-app', true],
    ['/srv/apps/shop', true],
    ['/home/deployer/app', true],
    ['/', false],
    ['/var/www', false],
    ['/etc', false],
    ['/home', false],
    ['var/www/app', false],
    ['/var/www/../../etc', false],
    ['/var/www/./app', false],
    ['/var/www/app; rm -rf /', false],
    ['/var/www/my app', false],
    ['/var/www/$(id)', false],
    ['/var/www/-rf', false],
]);

it('accepts relative shared paths only', function (string $path, bool $valid) {
    expect(ProjectPatterns::isSharedPath($path))->toBe($valid);
})->with([
    ['.env', true],
    ['storage/', true],
    ['public/uploads/', true],
    ['config/app-secrets.json', true],
    ['/etc/passwd', false],
    ['../outside', false],
    ['storage/../../x', false],
    ['-rf', false],
    ['a b', false],
    ['$(id)', false],
]);

it('accepts php-fpm service names only', function (string $service, bool $valid) {
    expect(ProjectPatterns::isPhpFpmService($service))->toBe($valid);
})->with([
    ['php8.3-fpm', true],
    ['php8.4-fpm', true],
    ['php-fpm', false],
    ['nginx', false],
    ['php8.3-fpm; reboot', false],
    ['php8.3-fpm nginx', false],
]);
