<?php

use App\Http\Controllers\GithubWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Public webhook endpoints. Loaded outside the "web" group: no session, no
 * CSRF, no setup redirect. Requests are authenticated by their HMAC signature.
 */

Route::post('webhooks/github/{slug}', GithubWebhookController::class)
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:webhooks')
    ->name('webhooks.github');
