<?php

use App\Http\Controllers\ServerConnectionController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('setup', [SetupController::class, 'create'])->name('setup.create');
    Route::post('setup', [SetupController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('setup.store');
});

Route::middleware('auth')->group(function () {
    Route::inertia('/', 'Dashboard')->name('dashboard');

    Route::resource('servers', ServerController::class);
    Route::post('servers/{server}/test', [ServerConnectionController::class, 'test'])
        ->middleware('throttle:20,1')
        ->name('servers.test');
    Route::post('servers/{server}/check', [ServerConnectionController::class, 'check'])
        ->middleware('throttle:20,1')
        ->name('servers.check');
});

require __DIR__.'/settings.php';
