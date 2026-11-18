<?php

namespace App\Domain\Servers\Actions;

use App\Domain\Servers\Enums\ServerStatus;
use App\Domain\Servers\Models\Server;

/**
 * SRV-5: edit connection details. The key pair never changes, so the key the
 * user already authorized keeps working.
 */
final class UpdateServer
{
    /**
     * @param  array{name: string, host: string, port: int, username: string}  $attributes
     */
    public function handle(Server $server, array $attributes): Server
    {
        $server->fill($attributes);

        // A new destination has not been checked yet.
        if ($server->isDirty(['host', 'port', 'username'])) {
            $server->status = ServerStatus::Unknown;
            $server->last_checked_at = null;
            $server->requirements = null;
        }

        $server->save();

        return $server;
    }
}
