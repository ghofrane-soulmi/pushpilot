<?php

namespace App\Domain\Deployments\Webhooks;

use SensitiveParameter;

/**
 * DEP-3: verifies GitHub's X-Hub-Signature-256 header, an HMAC SHA-256 of the
 * raw request body keyed with the project's webhook secret.
 *
 * https://docs.github.com/webhooks/using-webhooks/validating-webhook-deliveries
 */
final class GithubSignature
{
    private const PREFIX = 'sha256=';

    public static function isValid(string $payload, ?string $header, #[SensitiveParameter] string $secret): bool
    {
        if ($header === null || $secret === '' || ! str_starts_with($header, self::PREFIX)) {
            return false;
        }

        $received = substr($header, strlen(self::PREFIX));

        if (preg_match('/^[a-f0-9]{64}$/', $received) !== 1) {
            return false;
        }

        // Constant-time comparison: no timing hints about the expected value.
        return hash_equals(self::sign($payload, $secret), $received);
    }

    public static function sign(string $payload, #[SensitiveParameter] string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}
