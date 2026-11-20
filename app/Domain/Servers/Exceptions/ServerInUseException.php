<?php

namespace App\Domain\Servers\Exceptions;

use App\Domain\Servers\Models\Server;
use RuntimeException;

final class ServerInUseException extends RuntimeException
{
    public function __construct(Server $server, int $projectCount)
    {
        parent::__construct(sprintf(
            '%s is used by %d %s. Delete or move %s first.',
            $server->name,
            $projectCount,
            $projectCount === 1 ? 'project' : 'projects',
            $projectCount === 1 ? 'it' : 'them',
        ));
    }
}
