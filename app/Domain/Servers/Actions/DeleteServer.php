<?php

namespace App\Domain\Servers\Actions;

use App\Domain\Servers\Exceptions\ServerInUseException;
use App\Domain\Servers\Models\Server;

/**
 * SRV-5: delete a server and its key pair, unless projects still use it.
 * (The projects.server_id foreign key also restricts the delete.)
 */
final class DeleteServer
{
    public function handle(Server $server): void
    {
        $count = $server->projects()->count();

        if ($count > 0) {
            throw new ServerInUseException($server, $count);
        }

        $server->delete();
    }
}
