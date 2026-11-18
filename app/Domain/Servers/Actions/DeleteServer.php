<?php

namespace App\Domain\Servers\Actions;

use App\Domain\Servers\Models\Server;

/**
 * SRV-5: delete a server and its key pair.
 *
 * TODO(milestone 3): refuse while projects still use the server.
 */
final class DeleteServer
{
    public function handle(Server $server): void
    {
        $server->delete();
    }
}
