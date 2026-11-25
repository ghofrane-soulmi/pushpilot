<?php

use App\Domain\Projects\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->project = Project::factory()->initialized()->create(['slug' => 'shop']);
});

it('shows the webhook URL on the project page', function () {
    $this->actingAs($this->owner)
        ->get(route('projects.show', $this->project))
        ->assertInertia(fn (Assert $page) => $page->where('webhookUrl', url('/webhooks/github/shop')));
});

it('generates a new secret and shows it exactly once', function () {
    $old = $this->project->webhook_secret;

    $this->actingAs($this->owner)
        ->post(route('projects.webhook-secret', $this->project))
        ->assertRedirect(route('projects.show', $this->project));

    $secret = $this->project->refresh()->webhook_secret;

    expect($secret)->not->toBe($old)->and(strlen($secret))->toBe(48);

    // The next page view carries it once...
    $this->actingAs($this->owner)
        ->get(route('projects.show', $this->project))
        ->assertSee($secret, false);

    // ...and it never comes back after that.
    $this->actingAs($this->owner)
        ->get(route('projects.show', $this->project))
        ->assertDontSee($secret, false);
});

it('keeps secret generation behind login', function () {
    $this->post(route('projects.webhook-secret', $this->project))->assertRedirect(route('login'));
});
