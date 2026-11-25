<?php

use App\Domain\Deployments\Webhooks\GithubSignature;

$payload = '{"ref":"refs/heads/main"}';
$secret = 'It\'s a Secret to Everybody';

it('matches GitHub\'s documented example', function () {
    // From GitHub's docs: secret "It's a Secret to Everybody", payload "Hello, World!".
    expect(GithubSignature::sign('Hello, World!', "It's a Secret to Everybody"))
        ->toBe('757107ea0eb2509fc211221cce984b8a37570b6d7586c22c46f4379c8b043e17');
});

it('accepts a valid signature', function () use ($payload, $secret) {
    expect(GithubSignature::isValid($payload, 'sha256='.GithubSignature::sign($payload, $secret), $secret))->toBeTrue();
});

it('rejects anything else', function (?string $header) use ($payload, $secret) {
    expect(GithubSignature::isValid($payload, $header, $secret))->toBeFalse();
})->with([
    'missing header' => [null],
    'empty header' => [''],
    'wrong algorithm prefix' => ['sha1='.str_repeat('a', 40)],
    'no prefix' => [str_repeat('a', 64)],
    'right length, wrong value' => ['sha256='.str_repeat('0', 64)],
    'uppercase hex' => ['sha256='.strtoupper(hash_hmac('sha256', '{"ref":"refs/heads/main"}', 'It\'s a Secret to Everybody'))],
    'truncated' => ['sha256='.substr(hash_hmac('sha256', '{"ref":"refs/heads/main"}', 'It\'s a Secret to Everybody'), 0, 63)],
]);

it('rejects a signature made with another secret or for another body', function () use ($payload, $secret) {
    expect(GithubSignature::isValid($payload, 'sha256='.GithubSignature::sign($payload, 'other'), $secret))->toBeFalse()
        ->and(GithubSignature::isValid($payload.' ', 'sha256='.GithubSignature::sign($payload, $secret), $secret))->toBeFalse();
});

it('rejects everything when the secret is empty', function () use ($payload) {
    expect(GithubSignature::isValid($payload, 'sha256='.GithubSignature::sign($payload, ''), ''))->toBeFalse();
});
