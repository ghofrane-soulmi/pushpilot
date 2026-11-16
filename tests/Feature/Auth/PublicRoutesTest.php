<?php

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 * Spec 7.1 acceptance: no route except login, setup and the webhook endpoint is
 * reachable without authentication.
 */

it('requires authentication on every route except the public allowlist', function () {
    $public = [
        'login',                      // GET  /login
        'login.store',                // POST /login
        'logout',                     // POST /logout (no-op for guests)
        'two-factor.login',           // second step of the login flow
        'two-factor.login.store',
        'setup.create',               // first-run setup, 404 once an owner exists
        'setup.store',
        'storage.local',              // signed URLs only
        'storage.local.upload',
    ];

    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RoutingRoute $route) => in_array($route->getName(), $public, true))
        ->reject(fn (RoutingRoute $route) => $route->uri() === 'up') // container health check
        ->reject(function (RoutingRoute $route) {
            $middleware = $route->gatherMiddleware();

            return collect($middleware)->contains(fn ($m) => is_string($m)
                && (str_starts_with($m, 'auth') || str_contains($m, 'Horizon\Http\Middleware\Authenticate') || str_contains($m, 'DevTools\Http\Authorize')));
        })
        ->map(fn (RoutingRoute $route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

it('does not offer public registration or password reset', function (string $uri) {
    User::factory()->create();

    $this->get($uri)->assertNotFound();
})->with(['/register', '/forgot-password', '/reset-password/some-token']);

it('keeps the Horizon dashboard behind login', function () {
    User::factory()->create();

    $this->get('/horizon')->assertRedirect(route('login'));
});

it('lets the owner open the Horizon dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/horizon')
        ->assertOk();
});
