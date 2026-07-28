<?php

namespace App\Models\Agents;

use App\Models\User;
use Database\Factories\Agents\AgentEvalRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'suite_id', 'agent_id', 'triggered_by', 'status',
    'total', 'passed', 'failed', 'results', 'error', 'finished_at',
])]
class AgentEvalRun extends Model
{
    /** @use HasFactory<AgentEvalRunFactory> */
    use HasFactory;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'passed' => 'integer',
            'failed' => 'integer',
            'results' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AgentEvalSuite, $this>
     */
    public function suite(): BelongsTo
    {
        return $this->belongsTo(AgentEvalSuite::class, 'suite_id');
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
