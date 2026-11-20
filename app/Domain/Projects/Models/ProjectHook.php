<?php

namespace App\Domain\Projects\Models;

use App\Domain\Projects\Enums\HookStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-defined command run at a pipeline stage (PRJ-6). The command is the
 * owner's own shell code and runs as-is on their server, as the SSH user.
 *
 * @property int $id
 * @property int $project_id
 * @property HookStage $stage
 * @property string $command
 * @property int $order
 */
class ProjectHook extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['stage', 'command', 'order'];

    protected function casts(): array
    {
        return [
            'stage' => HookStage::class,
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
