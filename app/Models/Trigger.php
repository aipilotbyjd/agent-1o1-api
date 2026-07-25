<?php

namespace App\Models;

use Database\Factories\TriggerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'workspace_id', 'triggerable_type', 'triggerable_id', 'type', 'trigger_type_id',
    'config', 'token', 'is_active', 'last_run_at', 'created_by',
])]
class Trigger extends Model
{
    /** @use HasFactory<TriggerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function triggerable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<TriggerType, $this>
     */
    public function triggerType(): BelongsTo
    {
        return $this->belongsTo(TriggerType::class);
    }
}
