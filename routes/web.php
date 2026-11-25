<?php

use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectEnvironmentController;
use App\Http\Controllers\ProjectInitializationController;
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

    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/deploy', [DeploymentController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('projects.deploy');
    Route::get('deployments/{deployment}', [DeploymentController::class, 'show'])->name('deployments.show');
    Route::post('deployments/{deployment}/cancel', [DeploymentController::class, 'cancel'])->name('deployments.cancel');

    Route::post('projects/{project}/initialize', ProjectInitializationController::class)
        ->middleware('throttle:10,1')
        ->name('projects.initialize');

    Route::controller(ProjectEnvironmentController::class)
        ->prefix('projects/{project}/environment')
        ->name('projects.environment.')
        ->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::put('/', 'set')->middleware('throttle:30,1')->name('set');
            Route::post('replace', 'replace')->middleware('throttle:10,1')->name('replace');
            Route::delete('{key}', 'remove')->middleware('throttle:30,1')->name('remove');
        });
});

require __DIR__.'/settings.php';
