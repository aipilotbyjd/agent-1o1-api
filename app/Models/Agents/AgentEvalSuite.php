<?php

namespace App\Models\Agents;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentEvalSuiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agent_id', 'workspace_id', 'created_by', 'name', 'description'])]
class AgentEvalSuite extends Model
{
    /** @use HasFactory<AgentEvalSuiteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AgentEvalCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(AgentEvalCase::class, 'suite_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<AgentEvalRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AgentEvalRun::class, 'suite_id')->latest();
    }
}
