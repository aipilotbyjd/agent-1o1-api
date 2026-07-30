<?php

namespace App\Models\Agents;

use App\Models\Nodes\Node;
use App\Models\Runs\Run;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Agents\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id', 'name', 'slug', 'description', 'instructions',
    'provider', 'model', 'temperature', 'settings', 'created_by',
])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'settings' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The catalog nodes this agent may call as tools, each carrying the config bound
     * to it at attach time.
     *
     * @return BelongsToMany<Node, $this>
     */
    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class)
            ->using(AgentNode::class)
            ->withPivot(['config', 'exposed_fields'])
            ->withTimestamps();
    }

    /**
     * @return MorphMany<Run, $this>
     */
    public function runs(): MorphMany
    {
        return $this->morphMany(Run::class, 'runnable');
    }

    /**
     * @return HasMany<AgentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AgentVersion::class);
    }

    /**
     * @return HasMany<AgentKnowledge, $this>
     */
    public function knowledge(): HasMany
    {
        return $this->hasMany(AgentKnowledge::class);
    }

    /**
     * @return HasMany<AgentMemory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(AgentMemory::class);
    }

    /**
     * @return HasMany<AgentEvalSuite, $this>
     */
    public function evalSuites(): HasMany
    {
        return $this->hasMany(AgentEvalSuite::class);
    }

    /**
     * @return BelongsToMany<AgentSkill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(AgentSkill::class)->withTimestamps();
    }

    public function currentVersionNumber(): ?int
    {
        return $this->versions()->max('version');
    }

    /**
     * Snapshot the agent's behavioral configuration as the next version.
     */
    public function snapshotVersion(?User $changedBy = null): AgentVersion
    {
        return $this->versions()->create([
            'version' => ((int) $this->versions()->max('version')) + 1,
            'snapshot' => [
                'instructions' => $this->instructions,
                'provider' => $this->provider,
                'model' => $this->model,
                'temperature' => $this->temperature,
                'settings' => $this->settings,
                'nodes' => $this->nodes()->get()->map(fn (Node $node): array => [
                    'node_id' => $node->id,
                    'config' => $node->pivot->config,
                    'exposed_fields' => $node->pivot->exposed_fields,
                ])->all(),
            ],
            'changed_by' => $changedBy?->id,
        ]);
    }
}
