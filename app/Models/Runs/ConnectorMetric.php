<?php

namespace App\Models\Runs;

use App\Models\Workspaces\Workspace;
use Database\Factories\Runs\ConnectorMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'connector', 'date',
    'total_calls', 'success_calls', 'failed_calls', 'total_duration_ms',
])]
class ConnectorMetric extends Model
{
    /** @use HasFactory<ConnectorMetricFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_calls' => 'integer',
            'success_calls' => 'integer',
            'failed_calls' => 'integer',
            'total_duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
