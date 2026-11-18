<?php

use App\Domain\Servers\Rules\Host;
use App\Domain\Servers\Support\SshKeyPair;
use phpseclib3\Crypt\EC\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;

it('generates an OpenSSH ed25519 key pair', function () {
    $keys = SshKeyPair::generate('pushpilot@production-1');

    expect($keys->privateKey)->toStartWith('-----BEGIN OPENSSH PRIVATE KEY-----')
        ->and($keys->publicKey)->toMatch('/^ssh-ed25519 [A-Za-z0-9+\/]+=* pushpilot@production-1$/');
});

it('generates a public key that matches the private key', function () {
    $keys = SshKeyPair::generate();

    $private = PublicKeyLoader::load($keys->privateKey);

    expect($private)->toBeInstanceOf(PrivateKey::class)
        ->and(trim($private->getPublicKey()->toString('OpenSSH', ['comment' => 'pushpilot'])))->toBe($keys->publicKey);
});

it('never generates the same key twice', function () {
    expect(SshKeyPair::generate()->publicKey)->not->toBe(SshKeyPair::generate()->publicKey);
});

it('redacts the private key when dumped', function () {
    expect(print_r(SshKeyPair::generate(), true))
        ->toContain('[redacted]')
        ->not->toContain('PRIVATE KEY');
});

it('validates hosts', function (string $host, bool $valid) {
    expect(Host::isValid($host))->toBe($valid);
})->with([
    ['203.0.113.10', true],
    ['2001:db8::1', true],
    ['vps.example.com', true],
    ['target', true],
    ['my-server-01.eu-west.example.org', true],
    ['', false],
    ['-oProxyCommand=sh', false],
    ['example.com;id', false],
    ['exa mple.com', false],
    ['$(id)', false],
    ['`id`', false],
    ['user@example.com', false],
    ['example.com:22', false],
    ['-example.com', false],
    [str_repeat('a', 64).'.com', false],
]);
