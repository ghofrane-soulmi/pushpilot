<?php

use App\Domain\Deployments\Actions\CancelDeployment;
use App\Domain\Deployments\Actions\QueueDeployment;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Enums\DeploymentTrigger;
use App\Domain\Deployments\Exceptions\ProjectNotReadyException;
use App\Domain\Deployments\Jobs\DeployJob;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Deployments\Pipeline\Pipeline;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use App\Support\Ssh\FakeSshClient;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;

it('queues a deployment on the deployments queue', function () {
    Queue::fake();
    $user = User::factory()->create();
    $project = Project::factory()->initialized()->create();

    $deployment = app(QueueDeployment::class)->handle($project, $user);

    expect($deployment->status)->toBe(DeploymentStatus::Queued)
        ->and($deployment->trigger)->toBe(DeploymentTrigger::Manual)
        ->and($deployment->user_id)->toBe($user->id);

    Queue::assertPushedOn('deployments', DeployJob::class, fn (DeployJob $job) => $job->deployment->is($deployment)
        && $job->connection === 'deployments');
});

it('refuses to queue a project that is not initialized', function () {
    Queue::fake();

    app(QueueDeployment::class)->handle(Project::factory()->create(), null);
})->throws(ProjectNotReadyException::class);

it('locks per project so one deployment runs at a time', function () {
    $deployment = Deployment::factory()->create();
    $middleware = (new DeployJob($deployment))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe('project:'.$deployment->project_id);
});

it('never retries a deployment that threw', function () {
    $job = new DeployJob(Deployment::factory()->create());

    expect($job->maxExceptions)->toBe(1)
        ->and($job->failOnTimeout)->toBeTrue()
        ->and($job->timeout)->toBeGreaterThan((int) config('pushpilot.deployments.global_timeout'));
});

it('skips a deployment cancelled while it waited in the queue', function () {
    $ssh = FakeSshClient::swap();
    $deployment = Deployment::factory()->create(['status' => DeploymentStatus::Cancelled]);

    (new DeployJob($deployment))->handle(app(Pipeline::class));

    $ssh->assertNothingRan();
    expect($deployment->refresh()->status)->toBe(DeploymentStatus::Cancelled);
});

it('marks the deployment failed when the job dies', function (bool $started, string $message) {
    $deployment = Deployment::factory()->create([
        'status' => $started ? DeploymentStatus::Running : DeploymentStatus::Queued,
        'started_at' => $started ? now() : null,
    ]);

    (new DeployJob($deployment))->failed(new RuntimeException('worker killed'));

    $deployment->refresh();
    expect($deployment->status)->toBe(DeploymentStatus::Failed)
        ->and($deployment->error_message)->toContain($message)
        ->and($deployment->finished_at)->not->toBeNull();
})->with([
    'while running' => [true, 'worker stopped'],
    'while waiting' => [false, 'waited too long'],
]);

it('does not touch a deployment that already finished when the job fails late', function () {
    $deployment = Deployment::factory()->succeeded()->create();

    (new DeployJob($deployment))->failed(new RuntimeException('late'));

    expect($deployment->refresh()->status)->toBe(DeploymentStatus::Succeeded);
});

describe('DEP-8: cancel', function () {
    it('cancels a queued deployment at once', function () {
        $deployment = Deployment::factory()->create();

        expect(app(CancelDeployment::class)->handle($deployment))->toBeTrue()
            ->and($deployment->refresh()->status)->toBe(DeploymentStatus::Cancelled);
    });

    it('asks a running deployment to stop before its next step', function () {
        $deployment = Deployment::factory()->running()->create();

        app(CancelDeployment::class)->handle($deployment);

        expect($deployment->refresh()->status)->toBe(DeploymentStatus::Running)
            ->and($deployment->cancel_requested_at)->not->toBeNull();
    });

    it('cannot cancel a finished deployment', function () {
        expect(app(CancelDeployment::class)->handle(Deployment::factory()->succeeded()->create()))->toBeFalse();
    });
});

it('fails deployments abandoned by a crashed worker', function () {
    $stuck = Deployment::factory()->running()->create(['started_at' => now()->subHours(2)]);
    $recent = Deployment::factory()->running()->create(['started_at' => now()->subMinute()]);
    $neverPicked = Deployment::factory()->create(['created_at' => now()->subHours(5)]);

    $this->artisan('deployments:fail-stale')->assertSuccessful();

    expect($stuck->refresh()->status)->toBe(DeploymentStatus::Failed)
        ->and($stuck->error_message)->toContain('worker stopped')
        ->and($recent->refresh()->status)->toBe(DeploymentStatus::Running)
        ->and($neverPicked->refresh()->status)->toBe(DeploymentStatus::Failed)
        ->and($neverPicked->error_message)->toContain('Horizon');
});

it('schedules the stale check every minute', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('deployments:fail-stale');
});
