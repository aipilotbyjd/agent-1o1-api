<?php

namespace App\Models\Nodes;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Agents\Agent;
use App\Models\Credentials\Credential;
use App\Models\Workspaces\Workspace;
use Database\Factories\Nodes\NodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'category_id',
    'workspace_id',
    'step_type',
    'type',
    'version',
    'name',
    'description',
    'icon',
    'color',
    'config_schema',
    'config',
    'input_schema',
    'output_schema',
    'credential_type',
    'credential_id',
    'cost_hint_usd',
    'latency_hint_ms',
    'is_active',
    'is_premium',
    'is_custom',
    'docs_url',
])]
class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_type' => WorkflowStepType::class,
            'version' => 'integer',
            'config_schema' => 'array',
            'config' => 'array',
            'input_schema' => 'array',
            'output_schema' => 'array',
            'cost_hint_usd' => 'decimal:4',
            'latency_hint_ms' => 'integer',
            'is_active' => 'boolean',
            'is_premium' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<NodeCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NodeCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Credential, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    /**
     * The agents that may call this node as a tool.
     *
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class)
            ->withPivot(['config', 'exposed_fields'])
            ->withTimestamps();
    }
}
