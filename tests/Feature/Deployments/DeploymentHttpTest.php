<?php

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Jobs\DeployJob;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

it('queues a deployment from the Deploy now button and opens it', function () {
    Queue::fake();
    $project = Project::factory()->initialized()->create();

    $response = $this->actingAs($this->owner)->post(route('projects.deploy', $project));

    $deployment = Deployment::sole();
    $response->assertRedirect(route('deployments.show', $deployment));

    expect($deployment->user_id)->toBe($this->owner->id)
        ->and($deployment->status)->toBe(DeploymentStatus::Queued);
    Queue::assertPushed(DeployJob::class);
});

it('asks to initialize first', function () {
    Queue::fake();
    $project = Project::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('projects.deploy', $project))
        ->assertRedirect(route('projects.show', $project))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Deployment::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('shows the deployment with its steps and masked log', function () {
    $deployment = Deployment::factory()->succeeded()->create();
    DB::table('deployment_logs')->insert([
        ['deployment_id' => $deployment->id, 'step' => 'clone', 'line' => 'Cloning into releases/x', 'stream' => 'stdout', 'created_at' => now()],
        ['deployment_id' => $deployment->id, 'step' => 'composer', 'line' => 'Installing dependencies', 'stream' => 'stderr', 'created_at' => now()],
    ]);

    $this->actingAs($this->owner)
        ->get(route('deployments.show', $deployment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('deployments/Show')
            ->where('deployment.status', 'succeeded')
            ->where('deployment.commit_message', 'Initial commit')
            ->has('deployment.log', 2)
            ->where('deployment.log.1.stream', 'stderr')
            ->where('deployment.steps.0.name', 'prepare')
            ->where('deployment.steps.1.ran', true)
            ->where('deployment.steps.0.ran', false));
});

it('lists recent deployments on the project page', function () {
    $project = Project::factory()->initialized()->create();
    Deployment::factory()->for($project)->succeeded()->count(2)->create();
    Deployment::factory()->for($project)->running()->create();

    $this->actingAs($this->owner)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->has('deployments', 3)
            ->where('deployments.0.status', 'running')
            ->where('hasActiveDeployment', true));
});

it('cancels a deployment', function () {
    $deployment = Deployment::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('deployments.cancel', $deployment))
        ->assertRedirect(route('deployments.show', $deployment));

    expect($deployment->refresh()->status)->toBe(DeploymentStatus::Cancelled);
});

it('keeps deployment routes behind login', function (string $method, string $routeName) {
    $deployment = Deployment::factory()->create();
    $parameter = $routeName === 'projects.deploy' ? $deployment->project : $deployment;

    $this->call($method, route($routeName, $parameter))->assertRedirect(route('login'));
})->with([
    ['POST', 'projects.deploy'],
    ['GET', 'deployments.show'],
    ['POST', 'deployments.cancel'],
]);
