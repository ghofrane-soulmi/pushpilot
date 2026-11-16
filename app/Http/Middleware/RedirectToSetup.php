<?php

namespace App\Http\Middleware;

use App\Actions\Setup\CreateOwnerAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends every web request to the first-run setup screen until the owner
 * account exists (AUTH-2).
 */
class RedirectToSetup
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('setup.*') || CreateOwnerAccount::isComplete()) {
            return $next($request);
        }

        return redirect()->route('setup.create');
    }
}
