<?php

namespace App\Domain\Deployments\Models;

use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Enums\DeploymentTrigger;
use App\Domain\Deployments\Enums\DeploymentType;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One execution of the pipeline (DEP-6).
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $user_id
 * @property DeploymentType $type
 * @property DeploymentTrigger $trigger
 * @property DeploymentStatus $status
 * @property string|null $commit_hash
 * @property string|null $commit_message
 * @property string|null $commit_author
 * @property string|null $release_name
 * @property string|null $current_step
 * @property list<string>|null $warnings
 * @property string|null $error_message
 * @property CarbonImmutable|null $cancel_requested_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read User|null $user
 */
class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'trigger'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'deploy',
        'trigger' => 'manual',
        'status' => 'queued',
    ];

    protected function casts(): array
    {
        return [
            'type' => DeploymentType::class,
            'trigger' => DeploymentTrigger::class,
            'status' => DeploymentStatus::class,
            'warnings' => 'array',
            'cancel_requested_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DeploymentLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(DeploymentLog::class)->orderBy('id');
    }

    public function durationInSeconds(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at ?? now(), true);
    }

    public function addWarning(string $warning): void
    {
        $this->warnings = [...($this->warnings ?? []), $warning];
    }

    protected static function newFactory(): DeploymentFactory
    {
        return DeploymentFactory::new();
    }
}
