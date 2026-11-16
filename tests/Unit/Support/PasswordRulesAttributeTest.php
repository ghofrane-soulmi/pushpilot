<?php

use App\Support\PasswordRulesAttribute;
use Illuminate\Validation\Rules\Password;

it('describes a minimal rule', function () {
    expect(PasswordRulesAttribute::for(Password::min(8)))
        ->toBe('minlength: 8;');
});

it('describes the production password policy', function () {
    $rule = Password::min(12)->max(64)->mixedCase()->letters()->numbers()->symbols();

    expect(PasswordRulesAttribute::for($rule))
        ->toBe('minlength: 12; maxlength: 64; required: lower; required: upper; required: digit; required: special;');
});

it('requires any letter case when only letters are enforced', function () {
    expect(PasswordRulesAttribute::for(Password::min(10)->letters()))
        ->toBe('minlength: 10; required: lower, upper;');
});
