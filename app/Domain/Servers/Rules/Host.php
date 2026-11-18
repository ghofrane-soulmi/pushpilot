<?php

namespace App\Domain\Servers\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An IPv4/IPv6 address or an RFC 1123 hostname. Anything else (spaces, shell
 * metacharacters, user@host, URLs) is rejected.
 */
final class Host implements ValidationRule
{
    private const HOSTNAME = '/^(?=.{1,253}$)[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('The :attribute must be an IP address or a hostname.');
        }
    }

    public static function isValid(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false
            || preg_match(self::HOSTNAME, $value) === 1;
    }
}
