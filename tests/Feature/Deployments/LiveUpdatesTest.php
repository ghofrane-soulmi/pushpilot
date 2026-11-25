<?php

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Events\DeploymentLogged;
use App\Domain\Deployments\Events\DeploymentUpdated;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Deployments\Pipeline\Pipeline;
use App\Domain\Deployments\Pipeline\SecretMasker;
use App\Domain\Deployments\Support\LiveUpdates;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use App\Support\Ssh\FakeSshClient;
use Illuminate\Support\Facades\Event;

beforeEach(fn () => LiveUpdates::reset());
afterEach(fn () => LiveUpdates::reset());

function deployWithSecretOutput(): Deployment
{
    FakeSshClient::swap()->respondToPattern('/artisan optimize/', "Caching... DB_PASSWORD=hunter2-very-secret\n");

    $project = Project::factory()->initialized()->withEnv("DB_PASSWORD=hunter2-very-secret\n")->create();
    $deployment = Deployment::factory()->for($project)->create();

    app(Pipeline::class)->run($deployment);

    return $deployment->refresh();
}

it('broadcasts every log line, masked, on the deployment\'s private channel', function () {
    Event::fake([DeploymentLogged::class]);

    $deployment = deployWithSecretOutput();

    Event::assertDispatched(DeploymentLogged::class, fn (DeploymentLogged $event) => $event->deploymentId === $deployment->id
        && $event->broadcastOn()->name === 'private-deployments.'.$deployment->id
        && $event->broadcastAs() === 'log');

    $broadcast = collect(Event::dispatched(DeploymentLogged::class))
        ->flatMap(fn (array $call) => $call[0]->lines)
        ->pluck('line')
        ->implode("\n");

    expect($broadcast)->toContain('▶ Clone repository')
        ->toContain(SecretMasker::MASK)
        ->not->toContain('hunter2-very-secret');
});

it('broadcasts status and step changes', function () {
    Event::fake([DeploymentUpdated::class]);

    $deployment = deployWithSecretOutput();

    $updates = collect(Event::dispatched(DeploymentUpdated::class))->map(fn (array $call) => $call[0]->broadcastWith());

    expect($updates->pluck('status')->unique()->values()->all())->toContain('running', 'succeeded')
        ->and($updates->pluck('current_step')->filter()->unique()->values()->all())->toContain('clone', 'activate')
        ->and($updates->last()['id'])->toBe($deployment->id);
});

it('keeps deploying when the WebSocket server is down, and tries only once', function () {
    $attempts = 0;
    Event::listen(DeploymentLogged::class, function () use (&$attempts) {
        $attempts++;

        throw new RuntimeException('Reverb unreachable');
    });

    $deployment = deployWithSecretOutput();

    expect($deployment->status)->toBe(DeploymentStatus::Succeeded)
        ->and($attempts)->toBe(1)
        ->and($deployment->logs()->count())->toBeGreaterThan(5);
});

it('lets the owner subscribe and keeps guests out', function () {
    $deployment = Deployment::factory()->create();

    $this->post('/broadcasting/auth', ['channel_name' => 'private-deployments.'.$deployment->id, 'socket_id' => '1.1'])
        ->assertStatus(302);

    $this->actingAs(User::factory()->create())
        ->post('/broadcasting/auth', ['channel_name' => 'private-deployments.'.$deployment->id, 'socket_id' => '1.1'])
        ->assertOk();
});
