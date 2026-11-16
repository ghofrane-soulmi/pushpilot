<?php

namespace App\Http\Controllers;

use App\Actions\Setup\CreateOwnerAccount;
use App\Http\Requests\SetupRequest;
use App\Support\PasswordRulesAttribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-run setup screen (AUTH-2): only reachable while no user exists.
 */
class SetupController extends Controller
{
    public function create(): Response
    {
        abort_if(CreateOwnerAccount::isComplete(), 404);

        return Inertia::render('auth/Setup', [
            'passwordRules' => PasswordRulesAttribute::defaults(),
        ]);
    }

    public function store(SetupRequest $request, CreateOwnerAccount $createOwner): RedirectResponse
    {
        abort_if(CreateOwnerAccount::isComplete(), 404);

        /** @var array{name: string, email: string, password: string} $attributes */
        $attributes = $request->safe()->only(['name', 'email', 'password']);

        $owner = $createOwner->handle($attributes);

        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
