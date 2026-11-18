<?php

namespace App\Http\Resources;

use App\Domain\Servers\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the browser may know about a server. The private key is deliberately
 * absent: it never leaves the backend after creation.
 *
 * @mixin Server
 */
class ServerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'status' => $this->status->value,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Detail view: adds the public key, the authorize command and the last
     * requirements report.
     *
     * @return array<string, mixed>
     */
    public function withDetails(Request $request): array
    {
        /** @var Server $server */
        $server = $this->resource;

        return [
            ...$this->toArray($request),
            'public_key' => $server->public_key,
            'authorize_command' => $server->authorizeKeyCommand(),
            'requirements' => $server->requirements,
        ];
    }
}
