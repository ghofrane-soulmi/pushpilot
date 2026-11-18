<?php

namespace App\Domain\Servers\Actions;

use App\Domain\Servers\Models\Server;
use App\Domain\Servers\Support\SshKeyPair;

/**
 * SRV-1 / SRV-2: store the server and generate its dedicated ed25519 key pair.
 */
final class CreateServer
{
    /**
     * @param  array{name: string, host: string, port: int, username: string}  $attributes
     */
    public function handle(array $attributes): Server
    {
        $keys = SshKeyPair::generate('pushpilot@'.$attributes['name']);

        $server = new Server($attributes);
        $server->private_key = $keys->privateKey;
        $server->public_key = $keys->publicKey;
        $server->save();

        return $server;
    }
}
