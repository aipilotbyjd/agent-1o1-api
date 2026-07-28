<?php

namespace App\Models\Triggers;

use App\Models\Runs\Run;
use Database\Factories\Triggers\TriggerEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'trigger_id', 'source', 'matched', 'run_id', 'payload_snippet', 'headers', 'error', 'delivery_id',
])]
class TriggerEvent extends Model
{
    /** @use HasFactory<TriggerEventFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'matched' => 'boolean',
            'headers' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Trigger, $this>
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(Trigger::class);
    }

    /**
     * @return BelongsTo<Run, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }
}
