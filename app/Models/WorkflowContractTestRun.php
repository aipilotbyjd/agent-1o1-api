<?php

namespace App\Models;

use Database\Factories\WorkflowContractTestRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contract_id', 'status', 'results'])]
class WorkflowContractTestRun extends Model
{
    /** @use HasFactory<WorkflowContractTestRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'results' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WorkflowContractSnapshot, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(WorkflowContractSnapshot::class, 'contract_id');
    }
}
