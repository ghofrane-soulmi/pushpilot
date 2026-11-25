<?php

use App\Domain\Deployments\Enums\DeploymentTrigger;
use App\Domain\Deployments\Jobs\DeployJob;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Deployments\Webhooks\GithubSignature;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Queue::fake();
    User::factory()->create();

    $this->project = Project::factory()->initialized()->create([
        'slug' => 'shop',
        'branch' => 'main',
        'webhook_secret' => 'correct-horse-battery-staple',
    ]);
});

function pushPayload(string $ref = 'refs/heads/main', array $extra = []): string
{
    return json_encode([
        'ref' => $ref,
        'deleted' => false,
        'head_commit' => [
            'id' => str_repeat('b', 40),
            'message' => "Fix checkout totals\n\nLonger description that should not be stored.",
            'author' => ['name' => 'Grace Hopper'],
        ],
        ...$extra,
    ], JSON_THROW_ON_ERROR);
}

function sendWebhook(string $payload, ?string $signature, string $event = 'push', string $slug = 'shop'): TestResponse
{
    $headers = ['HTTP_X_GITHUB_EVENT' => $event, 'CONTENT_TYPE' => 'application/json'];

    if ($signature !== null) {
        $headers['HTTP_X_HUB_SIGNATURE_256'] = $signature;
    }

    return test()->call('POST', "/webhooks/github/{$slug}", [], [], [], $headers, $payload);
}

function signed(string $payload, string $secret = 'correct-horse-battery-staple'): string
{
    return 'sha256='.GithubSignature::sign($payload, $secret);
}

it('queues a deployment for a signed push to the deployed branch', function () {
    $payload = pushPayload();

    sendWebhook($payload, signed($payload))
        ->assertStatus(202)
        ->assertJsonPath('message', 'Deployment queued.');

    $deployment = Deployment::sole();

    expect($deployment->trigger)->toBe(DeploymentTrigger::Webhook)
        ->and($deployment->user_id)->toBeNull()
        ->and($deployment->commit_hash)->toBe(str_repeat('b', 40))
        ->and($deployment->commit_message)->toBe('Fix checkout totals')
        ->and($deployment->commit_author)->toBe('Grace Hopper');

    Queue::assertPushed(DeployJob::class);
});

it('rejects unsigned or wrongly signed requests before doing anything', function (?string $signature) {
    sendWebhook(pushPayload(), $signature)
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid signature.');

    expect(Deployment::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'missing' => [null],
    'wrong secret' => [fn () => signed(pushPayload(), 'guessed-secret')],
    'signature of another body' => [fn () => signed(pushPayload('refs/heads/other'))],
    'garbage' => ['sha256=not-even-hex'],
    'sha1 header format' => ['sha1=0123456789abcdef0123456789abcdef01234567'],
]);

it('rejects a request signed with the old secret after a new one is generated', function () {
    $payload = pushPayload();
    $oldSignature = signed($payload);

    $this->actingAs(User::first())->post(route('projects.webhook-secret', $this->project));

    sendWebhook($payload, $oldSignature)->assertUnauthorized();
});

it('answers GitHub\'s ping', function () {
    $payload = '{"zen":"Keep it logically awesome."}';

    sendWebhook($payload, signed($payload), 'ping')->assertOk()->assertJsonPath('message', 'pong');
    expect(Deployment::count())->toBe(0);
});

it('ignores pushes to other branches, deleted branches and other events', function (string $payload, string $event) {
    sendWebhook($payload, signed($payload), $event)->assertStatus(202);

    expect(Deployment::count())->toBe(0);
})->with([
    'other branch' => [pushPayload('refs/heads/feature/x'), 'push'],
    'tag' => [pushPayload('refs/tags/v1.0.0'), 'push'],
    'deleted branch' => [pushPayload('refs/heads/main', ['deleted' => true]), 'push'],
    'pull request event' => ['{"action":"opened"}', 'pull_request'],
]);

it('refuses to deploy a project that is not initialized', function () {
    $this->project->forceFill(['initialized_at' => null])->save();
    $payload = pushPayload();

    sendWebhook($payload, signed($payload))->assertStatus(409);
});

it('returns 404 for an unknown project', function () {
    $payload = pushPayload();

    sendWebhook($payload, signed($payload), slug: 'unknown')->assertNotFound();
});

it('needs no session or CSRF token', function () {
    $payload = pushPayload();

    sendWebhook($payload, signed($payload))
        ->assertStatus(202)
        ->assertCookieMissing(config('session.cookie'));
});

it('is rate limited', function () {
    $payload = '{"zen":"x"}';

    for ($i = 0; $i < 30; $i++) {
        sendWebhook($payload, signed($payload), 'ping')->assertOk();
    }

    sendWebhook($payload, signed($payload), 'ping')->assertTooManyRequests();
});

it('never reveals the secret in a response', function () {
    $payload = pushPayload();

    foreach ([signed($payload), 'sha256='.str_repeat('0', 64)] as $signature) {
        expect(sendWebhook($payload, $signature)->getContent())->not->toContain('correct-horse-battery-staple');
    }
});
