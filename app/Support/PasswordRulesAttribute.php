<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Builds the HTML `passwordrules` attribute value from a Password rule, so
 * password managers generate passwords that pass server-side validation.
 *
 * Laravel 13 ships this as Password::toPasswordRulesString(); this is the
 * Laravel 12 equivalent and can be removed after upgrading.
 */
final class PasswordRulesAttribute
{
    public static function for(Password $rule): string
    {
        $applied = $rule->appliedRules();

        $parts = ['minlength: '.$applied['min']];

        if ($applied['max'] !== null) {
            $parts[] = 'maxlength: '.$applied['max'];
        }

        if ($applied['mixedCase']) {
            $parts[] = 'required: lower';
            $parts[] = 'required: upper';
        } elseif ($applied['letters']) {
            $parts[] = 'required: lower, upper';
        }

        if ($applied['numbers']) {
            $parts[] = 'required: digit';
        }

        if ($applied['symbols']) {
            $parts[] = 'required: special';
        }

        return implode('; ', $parts).';';
    }

    public static function defaults(): string
    {
        return self::for(Password::default());
    }
}
