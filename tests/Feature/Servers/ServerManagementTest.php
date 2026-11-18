<?php

use App\Domain\Servers\Enums\ServerStatus;
use App\Domain\Servers\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
});

$valid = [
    'name' => 'production-1',
    'host' => '203.0.113.10',
    'port' => 22,
    'username' => 'deployer',
];

describe('SRV-1 / SRV-2: adding a server', function () use ($valid) {
    it('shows the create form', function () {
        $this->actingAs($this->owner)
            ->get(route('servers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('servers/Create'));
    });

    it('stores the server and generates an ed25519 key pair', function () use ($valid) {
        $response = $this->actingAs($this->owner)->post(route('servers.store'), $valid);

        $server = Server::sole();
        $response->assertRedirect(route('servers.show', $server));

        expect($server->name)->toBe('production-1')
            ->and($server->host)->toBe('203.0.113.10')
            ->and($server->port)->toBe(22)
            ->and($server->username)->toBe('deployer')
            ->and($server->status)->toBe(ServerStatus::Unknown)
            ->and($server->public_key)->toStartWith('ssh-ed25519 ')
            ->and($server->private_key)->toContain('BEGIN OPENSSH PRIVATE KEY');
    });

    it('defaults the port to 22', function () use ($valid) {
        unset($valid['port']);

        $this->actingAs($this->owner)->post(route('servers.store'), $valid)->assertSessionHasNoErrors();

        expect(Server::sole()->port)->toBe(22);
    });

    it('gives every server its own key', function () use ($valid) {
        $this->actingAs($this->owner)->post(route('servers.store'), $valid);
        $this->actingAs($this->owner)->post(route('servers.store'), [...$valid, 'name' => 'production-2']);

        [$first, $second] = Server::query()->orderBy('id')->get()->all();

        expect($first->public_key)->not->toBe($second->public_key);
    });

    it('rejects invalid or malicious connection details', function (array $override, string $field) use ($valid) {
        $this->actingAs($this->owner)
            ->from(route('servers.create'))
            ->post(route('servers.store'), [...$valid, ...$override])
            ->assertSessionHasErrors($field)
            ->assertRedirect(route('servers.create'));

        expect(Server::count())->toBe(0);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'shell injection in host' => [['host' => 'example.com; rm -rf /'], 'host'],
        'command substitution in host' => [['host' => '$(whoami).example.com'], 'host'],
        'user@host in host' => [['host' => 'root@example.com'], 'host'],
        'url as host' => [['host' => 'https://example.com'], 'host'],
        'leading dash in host' => [['host' => '-oProxyCommand=evil'], 'host'],
        'port too high' => [['port' => 70000], 'port'],
        'port zero' => [['port' => 0], 'port'],
        'shell injection in username' => [['username' => 'deployer;id'], 'username'],
        'uppercase username' => [['username' => 'Deployer'], 'username'],
        'username with space' => [['username' => 'deploy er'], 'username'],
    ]);

    it('accepts hostnames and IP addresses', function (string $host) use ($valid) {
        $this->actingAs($this->owner)
            ->post(route('servers.store'), [...$valid, 'host' => $host])
            ->assertSessionHasNoErrors();
    })->with(['vps.example.com', 'target', '2001:db8::10', '10.0.0.5']);
});

describe('secrets never leave the backend', function () use ($valid) {
    it('encrypts the private key at rest', function () use ($valid) {
        $this->actingAs($this->owner)->post(route('servers.store'), $valid);

        $raw = DB::table('servers')->value('private_key');

        expect($raw)->not->toContain('PRIVATE KEY')
            ->and(decrypt($raw, false))->toContain('BEGIN OPENSSH PRIVATE KEY');
    });

    it('never sends the private key to the browser', function (string $routeName) {
        $server = Server::factory()->create();

        $this->actingAs($this->owner)
            ->get(route($routeName, $routeName === 'servers.index' ? [] : $server))
            ->assertOk()
            ->assertDontSee('PRIVATE KEY', false)
            ->assertDontSee('private_key', false);
    })->with(['servers.index', 'servers.show', 'servers.edit']);

    it('hides the private key when the model is serialized', function () {
        expect(Server::factory()->create()->toArray())->not->toHaveKey('private_key');
    });
});

describe('viewing servers', function () {
    it('lists servers with their status', function () {
        Server::factory()->reachable()->create(['name' => 'alpha']);
        Server::factory()->create(['name' => 'beta']);

        $this->actingAs($this->owner)
            ->get(route('servers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Index')
                ->has('servers', 2)
                ->where('servers.0.name', 'alpha')
                ->where('servers.0.status', 'reachable')
                ->where('servers.1.status', 'unknown'));
    });

    it('shows the public key and the authorize command', function () {
        $server = Server::factory()->create();

        $this->actingAs($this->owner)
            ->get(route('servers.show', $server))
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Show')
                ->where('server.public_key', $server->public_key)
                ->where('server.authorize_command', fn (string $command) => str_contains($command, "'{$server->public_key}'")
                    && str_contains($command, '>> ~/.ssh/authorized_keys'))
                ->where('server.requirements', null));
    });
});

describe('SRV-5: editing and deleting', function () use ($valid) {
    it('updates connection details but keeps the key pair', function () use ($valid) {
        $server = Server::factory()->reachable()->create();
        $publicKey = $server->public_key;

        $this->actingAs($this->owner)
            ->put(route('servers.update', $server), [...$valid, 'host' => 'new.example.com'])
            ->assertRedirect(route('servers.show', $server));

        $server->refresh();

        expect($server->host)->toBe('new.example.com')
            ->and($server->public_key)->toBe($publicKey)
            ->and($server->status)->toBe(ServerStatus::Unknown)
            ->and($server->last_checked_at)->toBeNull();
    });

    it('keeps the status when only the name changes', function () {
        $server = Server::factory()->reachable()->create();

        $this->actingAs($this->owner)->put(route('servers.update', $server), [
            'name' => 'renamed',
            'host' => $server->host,
            'port' => $server->port,
            'username' => $server->username,
        ]);

        expect($server->refresh()->status)->toBe(ServerStatus::Reachable);
    });

    it('validates updates too', function () {
        $server = Server::factory()->create();

        $this->actingAs($this->owner)
            ->put(route('servers.update', $server), ['name' => 'x', 'host' => 'a b', 'port' => 22, 'username' => 'deployer'])
            ->assertSessionHasErrors('host');
    });

    it('deletes a server', function () {
        $server = Server::factory()->create();

        $this->actingAs($this->owner)
            ->delete(route('servers.destroy', $server))
            ->assertRedirect(route('servers.index'));

        expect(Server::count())->toBe(0);
    });
});

it('keeps every server route behind login', function (string $method, string $routeName) {
    $server = Server::factory()->create();
    $parameters = in_array($routeName, ['servers.index', 'servers.create', 'servers.store'], true) ? [] : $server;

    $this->call($method, route($routeName, $parameters))->assertRedirect(route('login'));
})->with([
    ['GET', 'servers.index'],
    ['GET', 'servers.create'],
    ['POST', 'servers.store'],
    ['GET', 'servers.show'],
    ['GET', 'servers.edit'],
    ['PUT', 'servers.update'],
    ['DELETE', 'servers.destroy'],
    ['POST', 'servers.test'],
    ['POST', 'servers.check'],
]);
