<?php

namespace App\Domain\Deployments\Models;

use App\Support\Ssh\OutputStream;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of deployment output, already masked (secrets never stored).
 *
 * @property int $id
 * @property int $deployment_id
 * @property string $step
 * @property string $line
 * @property OutputStream $stream
 * @property CarbonImmutable|null $created_at
 */
class DeploymentLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['step', 'line', 'stream'];

    protected function casts(): array
    {
        return [
            'stream' => OutputStream::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Deployment, $this>
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }
}
