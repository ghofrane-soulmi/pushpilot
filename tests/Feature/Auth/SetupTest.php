<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

$owner = [
    'name' => 'Ada Owner',
    'email' => 'Ada@Example.com',
    'password' => 'correct-horse-battery',
    'password_confirmation' => 'correct-horse-battery',
];

describe('before the owner exists', function () use ($owner) {
    it('redirects every page to the setup screen', function (string $uri) {
        $this->get($uri)->assertRedirect(route('setup.create'));
    })->with(['/', '/login', '/settings/profile']);

    it('renders the setup screen', function () {
        $this->get(route('setup.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Setup')
                ->has('passwordRules'));
    });

    it('creates the owner account and logs them in', function () use ($owner) {
        $this->post(route('setup.store'), $owner)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user = User::sole();

        expect($user->name)->toBe('Ada Owner')
            ->and($user->email)->toBe('ada@example.com')
            ->and($user->role)->toBe(UserRole::Owner)
            ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue();

        $this->assertAuthenticatedAs($user);
    });

    it('validates the owner details', function (array $override, string $field) use ($owner) {
        $this->from(route('setup.create'))
            ->post(route('setup.store'), [...$owner, ...$override])
            ->assertSessionHasErrors($field)
            ->assertRedirect(route('setup.create'));

        expect(User::count())->toBe(0);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'invalid email' => [['email' => 'not-an-email'], 'email'],
        'unconfirmed password' => [['password_confirmation' => 'something-else'], 'password'],
    ]);
});

describe('after the owner exists', function () use ($owner) {
    beforeEach(fn () => User::factory()->create());

    it('hides the setup screen', function () {
        $this->get(route('setup.create'))->assertNotFound();
    });

    it('refuses to create a second account', function () use ($owner) {
        $this->post(route('setup.store'), $owner)->assertNotFound();

        expect(User::count())->toBe(1);
    });

    it('no longer redirects to setup', function () {
        $this->get('/login')->assertOk();
    });
});
