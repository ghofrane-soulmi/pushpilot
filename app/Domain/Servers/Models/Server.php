<?php

namespace App\Domain\Servers\Models;

use App\Domain\Projects\Models\Project;
use App\Domain\Servers\Enums\ServerStatus;
use App\Support\Ssh\Shell;
use App\Support\Ssh\SshCredentials;
use Carbon\CarbonImmutable;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A remote Linux machine reachable over SSH.
 *
 * The private key is encrypted at rest and hidden from serialization; it is
 * only ever read to open an SSH session (see credentials()).
 *
 * @property int $id
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string $private_key
 * @property string $public_key
 * @property ServerStatus $status
 * @property CarbonImmutable|null $last_checked_at
 * @property array<string, mixed>|null $requirements
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    /**
     * Only connection details are mass assignable; keys, status and
     * requirements are set explicitly by actions.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'host', 'port', 'username'];

    /**
     * @var list<string>
     */
    protected $hidden = ['private_key'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'port' => 22,
        'status' => 'unknown',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'private_key' => 'encrypted',
            'status' => ServerStatus::class,
            'last_checked_at' => 'immutable_datetime',
            'requirements' => 'array',
        ];
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function credentials(): SshCredentials
    {
        return new SshCredentials($this->host, $this->port, $this->username, $this->private_key);
    }

    /**
     * The command the user runs on the server to authorize Pushpilot's key (SRV-2).
     */
    public function authorizeKeyCommand(): string
    {
        return 'mkdir -p ~/.ssh && chmod 700 ~/.ssh && echo '
            .Shell::quote($this->public_key)
            .' >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys';
    }

    protected static function newFactory(): ServerFactory
    {
        return ServerFactory::new();
    }
}
