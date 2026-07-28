<?php

namespace App\Models\Workflows;

use Database\Factories\Workflows\WorkflowStepEdgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workflow_id', 'from_step_id', 'to_step_id', 'condition'])]
class WorkflowStepEdge extends Model
{
    /** @use HasFactory<WorkflowStepEdgeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WorkflowStep, $this>
     */
    public function fromStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'from_step_id');
    }

    /**
     * @return BelongsTo<WorkflowStep, $this>
     */
    public function toStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'to_step_id');
    }
}
