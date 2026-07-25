<?php

namespace App\Models;

use App\Enums\WorkflowStepType;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workflow_id', 'key', 'type', 'config', 'position'])]
class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WorkflowStepType::class,
            'config' => 'array',
            'position' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return HasMany<WorkflowStepEdge, $this>
     */
    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(WorkflowStepEdge::class, 'from_step_id');
    }

    /**
     * @return HasMany<WorkflowStepEdge, $this>
     */
    public function incomingEdges(): HasMany
    {
        return $this->hasMany(WorkflowStepEdge::class, 'to_step_id');
    }
}
