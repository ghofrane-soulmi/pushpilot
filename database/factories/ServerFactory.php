<?php

namespace Database\Factories;

use App\Domain\Servers\Enums\ServerStatus;
use App\Domain\Servers\Models\Server;
use App\Domain\Servers\Support\SshKeyPair;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    private static ?SshKeyPair $keys = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Key generation is fast but not free; reuse one pair across factory calls.
        self::$keys ??= SshKeyPair::generate();

        return [
            'name' => fake()->unique()->domainWord().'-prod',
            'host' => fake()->ipv4(),
            'port' => 22,
            'username' => 'deployer',
            'private_key' => self::$keys->privateKey,
            'public_key' => self::$keys->publicKey,
            'status' => ServerStatus::Unknown,
        ];
    }

    public function reachable(): static
    {
        return $this->state(fn () => [
            'status' => ServerStatus::Reachable,
            'last_checked_at' => now(),
        ]);
    }
}
