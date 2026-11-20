<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\RemoteProject;
use App\Domain\Servers\Models\Server;
use App\Models\User;
use App\Support\Ssh\CommandResult;
use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshConnectionException;
use App\Support\Ssh\FakeSshClient;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;

function projectFlash(TestResponse $response, string $key): mixed
{
    return data_get($response->baseRequest->session()->get(SessionKey::FLASH_DATA, []), $key);
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->server = Server::factory()->create(['host' => '203.0.113.10', 'username' => 'deployer']);
    $this->ssh = FakeSshClient::swap();
});

describe('PRJ-4: write-only .env editor', function () {
    it('adds a variable, encrypts it and writes the file to the server', function () {
        $project = Project::factory()->for($this->server)->initialized()->create();

        $this->actingAs($this->owner)
            ->put(route('projects.environment.set', $project), ['key' => 'DB_PASSWORD', 'value' => 'hunter2'])
            ->assertRedirect(route('projects.environment.edit', $project))
            ->assertInertiaFlash('toast.type', 'success');

        $project->refresh();

        expect($project->env_content)->toBe("DB_PASSWORD=hunter2\n")
            ->and($project->env_synced_at)->not->toBeNull();

        $this->ssh->assertConnectedTo('203.0.113.10', 'deployer')
            ->assertRan((new RemoteProject($project))->writeEnvCommand("DB_PASSWORD=hunter2\n"));
    });

    it('updates and removes variables', function () {
        $project = Project::factory()->for($this->server)->initialized()->withEnv("APP_NAME=Old\nDB_PASSWORD=x\n")->create();

        $this->actingAs($this->owner)->put(route('projects.environment.set', $project), ['key' => 'APP_NAME', 'value' => 'New Name']);
        $this->actingAs($this->owner)->delete(route('projects.environment.remove', ['project' => $project, 'key' => 'DB_PASSWORD']));

        expect($project->refresh()->env_content)->toBe("APP_NAME='New Name'\n");
    });

    it('replaces the whole file', function () {
        $project = Project::factory()->for($this->server)->initialized()->withEnv("OLD=1\n")->create();

        $this->actingAs($this->owner)
            ->post(route('projects.environment.replace', $project), ['content' => "# prod\nAPP_ENV=production\nAPP_DEBUG=false"]);

        expect($project->refresh()->env_content)->toBe("# prod\nAPP_ENV=production\nAPP_DEBUG=false\n");
    });

    it('never sends values back, even right after saving', function () {
        $project = Project::factory()->for($this->server)->initialized()->create();

        $this->actingAs($this->owner)
            ->followingRedirects()
            ->put(route('projects.environment.set', $project), ['key' => 'STRIPE_SECRET', 'value' => 'sk_live_abc123'])
            ->assertOk()
            ->assertSee('STRIPE_SECRET', false)
            ->assertDontSee('sk_live_abc123', false);
    });

    it('rejects invalid names and multi-line values', function (array $input, string $field) {
        $project = Project::factory()->for($this->server)->initialized()->create();

        $this->actingAs($this->owner)
            ->put(route('projects.environment.set', $project), $input)
            ->assertSessionHasErrors($field);

        expect($project->refresh()->env_content)->toBeNull();
        $this->ssh->assertNothingRan();
    })->with([
        'name with dash' => [['key' => 'APP-NAME', 'value' => 'x'], 'key'],
        'name with space' => [['key' => 'APP NAME', 'value' => 'x'], 'key'],
        'newline injection' => [['key' => 'APP_KEY', 'value' => "x\nAPP_DEBUG=true"], 'value'],
    ]);

    it('stores the .env without touching the server before initialization', function () {
        $project = Project::factory()->for($this->server)->create();

        $response = $this->actingAs($this->owner)
            ->put(route('projects.environment.set', $project), ['key' => 'APP_ENV', 'value' => 'production'])
            ->assertInertiaFlash('toast.type', 'warning');

        expect(projectFlash($response, 'toast.message'))->toContain('when you initialize')
            ->and($project->refresh()->env_content)->toBe("APP_ENV=production\n")
            ->and($project->env_synced_at)->toBeNull();

        $this->ssh->assertNothingRan();
    });

    it('keeps the saved .env when the server is unreachable', function () {
        $project = Project::factory()->for($this->server)->initialized()->create();
        $this->ssh->failConnectionWith(new SshConnectionException('Connection refused'));

        $this->actingAs($this->owner)
            ->put(route('projects.environment.set', $project), ['key' => 'APP_ENV', 'value' => 'production'])
            ->assertInertiaFlash('toast.type', 'warning');

        expect($project->refresh()->env_content)->toBe("APP_ENV=production\n")
            ->and($project->env_synced_at)->toBeNull();
    });
});

describe('PRJ-10: initialize', function () {
    it('creates the structure, installs the deploy key, writes the .env and checks the repository', function () {
        $project = Project::factory()->for($this->server)->withEnv("APP_ENV=production\n")->create();
        $remote = new RemoteProject($project);

        $response = $this->actingAs($this->owner)
            ->from(route('projects.show', $project))
            ->post(route('projects.initialize', $project))
            ->assertRedirect(route('projects.show', $project))
            ->assertInertiaFlash('initialization.successful', true);

        $this->ssh->assertRanInOrder([
            $remote->ensureWritableRootCommand(),
            $remote->createStructureCommand(),
            $remote->installDeployKeyCommand(),
            $remote->writeEnvCommand("APP_ENV=production\n"),
            $remote->verifyRepositoryCommand(),
        ]);

        expect(collect(projectFlash($response, 'initialization.steps'))->pluck('ok')->all())->toBe([true, true, true, true, true]);

        $project->refresh();
        expect($project->initialized_at)->not->toBeNull()
            ->and($project->env_synced_at)->not->toBeNull();
    });

    it('never leaks the deploy key or .env into the result shown to the user', function () {
        $project = Project::factory()->for($this->server)->withEnv("DB_PASSWORD=hunter2\n")->create();

        $response = $this->actingAs($this->owner)->post(route('projects.initialize', $project));

        $flash = json_encode(projectFlash($response, 'initialization'));

        expect($flash)->not->toContain('PRIVATE KEY')
            ->not->toContain('hunter2')
            ->not->toContain(base64_encode("DB_PASSWORD=hunter2\n"));
    });

    it('stops with a fix hint when the deploy path is not writable', function () {
        $project = Project::factory()->for($this->server)->create(['deploy_path' => '/var/www/shop']);
        $this->ssh->respondTo((new RemoteProject($project))->ensureWritableRootCommand(), CommandResult::failure(1));

        $response = $this->actingAs($this->owner)
            ->post(route('projects.initialize', $project))
            ->assertInertiaFlash('initialization.successful', false);

        expect(projectFlash($response, 'initialization.steps.0.message'))->toContain('sudo chown deployer /var/www/shop')
            ->and($this->ssh->commands())->toHaveCount(1)
            ->and($project->refresh()->initialized_at)->toBeNull();
    });

    it('is initialized but warns when the repository is not readable yet', function () {
        $project = Project::factory()->for($this->server)->create();
        $this->ssh->respondTo((new RemoteProject($project))->verifyRepositoryCommand(), CommandResult::failure(128, 'Permission denied (publickey).'));

        $response = $this->actingAs($this->owner)
            ->post(route('projects.initialize', $project))
            ->assertInertiaFlash('initialization.successful', false);

        expect(projectFlash($response, 'initialization.message'))->toContain('repository is not readable')
            ->and(collect(projectFlash($response, 'initialization.steps'))->last()['message'])->toContain('Deploy keys')
            ->and($project->refresh()->initialized_at)->not->toBeNull();
    });

    it('explains when the server key is not authorized', function () {
        $project = Project::factory()->for($this->server)->create();
        $this->ssh->failConnectionWith(new SshAuthenticationException('rejected'));

        $response = $this->actingAs($this->owner)->post(route('projects.initialize', $project));

        expect(projectFlash($response, 'initialization.message'))->toContain('authorize the server key')
            ->and($project->refresh()->initialized_at)->toBeNull();
        $this->ssh->assertNothingRan();
    });
});
