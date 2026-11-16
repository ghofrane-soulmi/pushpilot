<?php

use App\Support\Ssh\FakeSshClient;
use App\Support\Ssh\PhpseclibSshClient;
use App\Support\Ssh\SshClient;

it('resolves the phpseclib client by default', function () {
    expect(app(SshClient::class))->toBeInstanceOf(PhpseclibSshClient::class);
});

it('can be swapped for the fake in tests', function () {
    $fake = FakeSshClient::swap();

    expect(app(SshClient::class))->toBe($fake);
});
