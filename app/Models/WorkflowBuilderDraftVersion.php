<?php

namespace App\Models;

use Database\Factories\WorkflowBuilderDraftVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['session_id', 'triggered_by', 'graph_snapshot', 'label'])]
class WorkflowBuilderDraftVersion extends Model
{
    /** @use HasFactory<WorkflowBuilderDraftVersionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graph_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WorkflowBuilderSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkflowBuilderSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
