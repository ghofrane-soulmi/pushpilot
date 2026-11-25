<?php

namespace App\Domain\Projects\Models;

use App\Domain\Deployments\Models\Deployment;
use App\Domain\Servers\Models\Server;
use App\Support\Ssh\Shell;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Git repository + branch deployed to a path on one server.
 *
 * Secrets (deploy private key, webhook secret, .env content) are encrypted at
 * rest and hidden from serialization; the browser never receives them.
 *
 * @property int $id
 * @property int $server_id
 * @property string $name
 * @property string $slug
 * @property string $repository_url
 * @property string $branch
 * @property string $deploy_path
 * @property string $deploy_private_key
 * @property string $deploy_public_key
 * @property string $webhook_secret
 * @property string|null $env_content
 * @property list<string> $shared_paths
 * @property string|null $php_fpm_service
 * @property bool $run_composer
 * @property bool $run_npm_build
 * @property bool $run_migrations
 * @property bool $queue_restart
 * @property string|null $health_check_url
 * @property int $health_check_status
 * @property bool $auto_rollback
 * @property int $keep_releases
 * @property CarbonImmutable|null $initialized_at
 * @property CarbonImmutable|null $env_synced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Server $server
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const DEFAULT_SHARED_PATHS = ['.env', 'storage/'];

    /**
     * Secrets, slug and server are set explicitly by actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'repository_url',
        'branch',
        'deploy_path',
        'shared_paths',
        'php_fpm_service',
        'run_composer',
        'run_npm_build',
        'run_migrations',
        'queue_restart',
        'health_check_url',
        'health_check_status',
        'auto_rollback',
        'keep_releases',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['deploy_private_key', 'webhook_secret', 'env_content'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'run_composer' => true,
        'run_npm_build' => false,
        'run_migrations' => false,
        'queue_restart' => false,
        'health_check_status' => 200,
        'auto_rollback' => false,
        'keep_releases' => 5,
    ];

    protected function casts(): array
    {
        return [
            'deploy_private_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'env_content' => 'encrypted',
            'shared_paths' => 'array',
            'run_composer' => 'boolean',
            'run_npm_build' => 'boolean',
            'run_migrations' => 'boolean',
            'queue_restart' => 'boolean',
            'health_check_status' => 'integer',
            'auto_rollback' => 'boolean',
            'keep_releases' => 'integer',
            'initialized_at' => 'immutable_datetime',
            'env_synced_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @return HasMany<ProjectHook, $this>
     */
    public function hooks(): HasMany
    {
        return $this->hasMany(ProjectHook::class)->orderBy('order');
    }

    /**
     * Path of the deploy key on the server, as a shell word ("$HOME" expands).
     */
    public function remoteDeployKeyPath(): string
    {
        return '"$HOME"/.ssh/'.Shell::quote('pushpilot_'.$this->slug);
    }

    /**
     * GIT_SSH_COMMAND so git on the server uses only this project's deploy key.
     */
    public function gitSshCommand(): string
    {
        return 'GIT_SSH_COMMAND='.Shell::quote(
            'ssh -i "$HOME/.ssh/pushpilot_'.$this->slug.'" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new'
        );
    }

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }
}
