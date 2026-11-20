<?php

use App\Domain\Projects\Enums\HookStage;
use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->server = Server::factory()->create([
        'requirements' => ['checks' => [], 'php_fpm_service' => 'php8.3-fpm', 'ready' => true],
    ]);
});

function projectInput(Server $server, array $overrides = []): array
{
    return [
        'name' => 'My Shop',
        'server_id' => $server->id,
        'repository_url' => 'git@github.com:acme/shop.git',
        'branch' => 'main',
        'deploy_path' => '/var/www/shop',
        ...$overrides,
    ];
}

function projectSettings(Project $project, array $overrides = []): array
{
    return [
        'name' => $project->name,
        'repository_url' => $project->repository_url,
        'branch' => $project->branch,
        'deploy_path' => $project->deploy_path,
        'shared_paths' => ['.env', 'storage/'],
        'run_composer' => true,
        'run_npm_build' => false,
        'run_migrations' => false,
        'queue_restart' => false,
        'php_fpm_service' => 'php8.3-fpm',
        'keep_releases' => 5,
        'health_check_url' => '',
        'health_check_status' => 200,
        'auto_rollback' => false,
        'hooks' => [],
        ...$overrides,
    ];
}

describe('PRJ-1 / PRJ-2: creating a project', function () {
    it('creates a project with a deploy key, a webhook secret and sensible defaults', function () {
        $response = $this->actingAs($this->owner)->post(route('projects.store'), projectInput($this->server));

        $project = Project::sole();
        $response->assertRedirect(route('projects.show', $project));

        expect($project->slug)->toBe('my-shop')
            ->and($project->server_id)->toBe($this->server->id)
            ->and($project->deploy_public_key)->toStartWith('ssh-ed25519 ')
            ->and($project->deploy_private_key)->toContain('BEGIN OPENSSH PRIVATE KEY')
            ->and(strlen($project->webhook_secret))->toBe(40)
            ->and($project->shared_paths)->toBe(['.env', 'storage/'])
            ->and($project->php_fpm_service)->toBe('php8.3-fpm')
            ->and($project->run_composer)->toBeTrue()
            ->and($project->run_npm_build)->toBeFalse()
            ->and($project->keep_releases)->toBe(5)
            ->and($project->initialized_at)->toBeNull();
    });

    it('gives projects unique slugs and their own keys', function () {
        $this->actingAs($this->owner)->post(route('projects.store'), projectInput($this->server));
        $this->actingAs($this->owner)->post(route('projects.store'), projectInput($this->server, ['deploy_path' => '/var/www/shop-2']));

        [$first, $second] = Project::query()->orderBy('id')->get()->all();

        expect($second->slug)->toBe('my-shop-2')
            ->and($first->deploy_public_key)->not->toBe($second->deploy_public_key);
    });

    it('strips a trailing slash from the deploy path', function () {
        $this->actingAs($this->owner)->post(route('projects.store'), projectInput($this->server, ['deploy_path' => '/var/www/shop/']));

        expect(Project::sole()->deploy_path)->toBe('/var/www/shop');
    });

    it('rejects invalid or malicious input', function (array $override, string $field) {
        $this->actingAs($this->owner)
            ->from(route('projects.create'))
            ->post(route('projects.store'), projectInput($this->server, $override))
            ->assertSessionHasErrors($field);

        expect(Project::count())->toBe(0);
    })->with([
        'https repository' => [['repository_url' => 'https://github.com/acme/shop.git'], 'repository_url'],
        'injection in repository' => [['repository_url' => 'git@github.com:acme/shop.git;id'], 'repository_url'],
        'option injection in branch' => [['branch' => '--upload-pack=touch /tmp/x'], 'branch'],
        'injection in branch' => [['branch' => 'main$(id)'], 'branch'],
        'relative deploy path' => [['deploy_path' => 'var/www/shop'], 'deploy_path'],
        'root deploy path' => [['deploy_path' => '/'], 'deploy_path'],
        'system deploy path' => [['deploy_path' => '/etc'], 'deploy_path'],
        'traversal in deploy path' => [['deploy_path' => '/var/www/../../root'], 'deploy_path'],
        'unknown server' => [['server_id' => 999], 'server_id'],
    ]);

    it('refuses two projects in the same path on one server', function () {
        Project::factory()->for($this->server)->create(['deploy_path' => '/var/www/shop']);

        $this->actingAs($this->owner)
            ->post(route('projects.store'), projectInput($this->server))
            ->assertSessionHasErrors('deploy_path');
    });
});

describe('secrets never reach the browser', function () {
    it('encrypts the deploy key, webhook secret and .env at rest', function () {
        Project::factory()->withEnv("DB_PASSWORD=hunter2\n")->create();

        $row = (array) DB::table('projects')->first();

        expect($row['deploy_private_key'])->not->toContain('PRIVATE KEY')
            ->and($row['env_content'])->not->toContain('hunter2')
            ->and(decrypt($row['env_content'], false))->toContain('hunter2')
            ->and(strlen((string) $row['webhook_secret']))->toBeGreaterThan(40);
    });

    it('keeps secrets out of every project page', function (string $routeName) {
        $project = Project::factory()->withEnv("DB_PASSWORD=hunter2\nAPP_KEY=base64:topsecret\n")->create();

        $response = $this->actingAs($this->owner)
            ->get(route($routeName, $routeName === 'projects.index' ? [] : $project))
            ->assertOk()
            ->assertDontSee('PRIVATE KEY', false)
            ->assertDontSee($project->webhook_secret, false)
            ->assertDontSee('hunter2', false)
            ->assertDontSee('topsecret', false);

        if ($routeName !== 'projects.index') {
            $response->assertInertia(fn (Assert $page) => $page
                ->where('project.environment.keys', ['DB_PASSWORD', 'APP_KEY']));
        }
    })->with(['projects.index', 'projects.show', 'projects.edit', 'projects.environment.edit']);

    it('hides secrets when the model is serialized', function () {
        expect(Project::factory()->withEnv()->create()->toArray())
            ->not->toHaveKeys(['deploy_private_key', 'webhook_secret', 'env_content']);
    });
});

describe('settings (PRJ-3, PRJ-5..PRJ-9)', function () {
    it('saves build, release, health-check settings and hooks', function () {
        $project = Project::factory()->for($this->server)->create();

        $this->actingAs($this->owner)
            ->put(route('projects.update', $project), projectSettings($project, [
                'shared_paths' => ['.env', 'storage/', 'public/uploads/'],
                'run_npm_build' => true,
                'run_migrations' => true,
                'queue_restart' => true,
                'keep_releases' => 10,
                'health_check_url' => 'https://shop.example.com/up',
                'auto_rollback' => true,
                'hooks' => [
                    ['stage' => 'after_build', 'command' => 'php artisan storage:link'],
                    ['stage' => 'before_activate', 'command' => 'php artisan config:cache'],
                    ['stage' => 'after_build', 'command' => '   '],
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.edit', $project));

        $project->refresh();

        expect($project->shared_paths)->toBe(['.env', 'storage/', 'public/uploads/'])
            ->and($project->run_npm_build)->toBeTrue()
            ->and($project->run_migrations)->toBeTrue()
            ->and($project->queue_restart)->toBeTrue()
            ->and($project->keep_releases)->toBe(10)
            ->and($project->health_check_url)->toBe('https://shop.example.com/up')
            ->and($project->auto_rollback)->toBeTrue()
            ->and($project->hooks->map(fn ($hook) => [$hook->stage, $hook->command])->all())->toBe([
                [HookStage::AfterBuild, 'php artisan storage:link'],
                [HookStage::BeforeActivate, 'php artisan config:cache'],
            ]);
    });

    it('requires a health-check URL for automatic rollback', function () {
        $project = Project::factory()->create();

        $this->actingAs($this->owner)
            ->put(route('projects.update', $project), projectSettings($project, ['auto_rollback' => true]))
            ->assertSessionHasErrors('auto_rollback');
    });

    it('rejects unsafe settings', function (array $override, string $field) {
        $project = Project::factory()->create();

        $this->actingAs($this->owner)
            ->put(route('projects.update', $project), projectSettings($project, $override))
            ->assertSessionHasErrors($field);
    })->with([
        'absolute shared path' => [['shared_paths' => ['/etc/passwd']], 'shared_paths.0'],
        'traversal shared path' => [['shared_paths' => ['../secrets']], 'shared_paths.0'],
        'duplicate shared path' => [['shared_paths' => ['.env', '.env']], 'shared_paths.1'],
        'injected fpm service' => [['php_fpm_service' => 'php8.3-fpm; reboot'], 'php_fpm_service'],
        'unknown hook stage' => [['hooks' => [['stage' => 'whenever', 'command' => 'ls']]], 'hooks.0.stage'],
        'too many releases' => [['keep_releases' => 500], 'keep_releases'],
        'non-http health check' => [['health_check_url' => 'ftp://example.com'], 'health_check_url'],
    ]);

    it('requires initializing again when the deploy path changes', function () {
        $project = Project::factory()->initialized()->create();

        $this->actingAs($this->owner)
            ->put(route('projects.update', $project), projectSettings($project, ['deploy_path' => '/var/www/elsewhere']))
            ->assertInertiaFlash('toast.type', 'warning');

        expect($project->refresh()->initialized_at)->toBeNull();
    });

    it('never changes the deploy key or webhook secret', function () {
        $project = Project::factory()->create();
        $key = $project->deploy_public_key;
        $secret = $project->webhook_secret;

        $this->actingAs($this->owner)->put(route('projects.update', $project), projectSettings($project, [
            'deploy_public_key' => 'ssh-ed25519 attacker',
            'webhook_secret' => 'known',
            'slug' => 'hijacked',
        ]));

        $project->refresh();

        expect($project->deploy_public_key)->toBe($key)
            ->and($project->webhook_secret)->toBe($secret)
            ->and($project->slug)->not->toBe('hijacked');
    });
});

describe('deleting', function () {
    it('deletes a project and its hooks', function () {
        $project = Project::factory()->create();
        $project->hooks()->create(['stage' => HookStage::AfterBuild, 'command' => 'ls', 'order' => 0]);

        $this->actingAs($this->owner)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        expect(Project::count())->toBe(0)
            ->and(DB::table('project_hooks')->count())->toBe(0);
    });

    it('refuses to delete a server that projects still use (SRV-5)', function () {
        Project::factory()->for($this->server)->create();

        $this->actingAs($this->owner)
            ->delete(route('servers.destroy', $this->server))
            ->assertRedirect(route('servers.show', $this->server))
            ->assertInertiaFlash('toast.type', 'error');

        expect(Server::find($this->server->id))->not->toBeNull();
    });
});

it('keeps every project route behind login', function (string $method, string $routeName) {
    $project = Project::factory()->create();
    $parameters = match ($routeName) {
        'projects.index', 'projects.create', 'projects.store' => [],
        'projects.environment.remove' => ['project' => $project, 'key' => 'APP_KEY'],
        default => $project,
    };

    $this->call($method, route($routeName, $parameters))->assertRedirect(route('login'));
})->with([
    ['GET', 'projects.index'],
    ['GET', 'projects.create'],
    ['POST', 'projects.store'],
    ['GET', 'projects.show'],
    ['GET', 'projects.edit'],
    ['PUT', 'projects.update'],
    ['DELETE', 'projects.destroy'],
    ['POST', 'projects.initialize'],
    ['GET', 'projects.environment.edit'],
    ['PUT', 'projects.environment.set'],
    ['POST', 'projects.environment.replace'],
    ['DELETE', 'projects.environment.remove'],
]);
