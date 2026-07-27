<?php

namespace App\Models\Workflows;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Workflows\WorkflowBuilderSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable([
    'workspace_id', 'user_id', 'workflow_id', 'conversation_id', 'title',
    'draft_graph', 'draft_lock_version', 'status', 'last_activity_at',
])]
class WorkflowBuilderSession extends Model
{
    /** @use HasFactory<WorkflowBuilderSessionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'title' => 'Untitled workflow',
        'draft_lock_version' => 0,
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'draft_graph' => 'array',
            'draft_lock_version' => 'integer',
            'last_activity_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return HasMany<WorkflowBuilderDraftVersion, $this>
     */
    public function draftVersions(): HasMany
    {
        return $this->hasMany(WorkflowBuilderDraftVersion::class, 'session_id');
    }

    /**
     * @return HasMany<WorkflowBuilderMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WorkflowBuilderMessage::class, 'session_id');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array{x: float, y: float}|null  $position
     */
    public function addStep(string $key, string $type, array $config = [], ?array $position = null, ?User $by = null): void
    {
        $graph = $this->currentGraph();

        if (collect($graph['steps'])->contains('key', $key)) {
            throw new InvalidArgumentException("Step [{$key}] already exists.");
        }

        $graph['steps'][] = ['key' => $key, 'type' => $type, 'config' => $config, 'position' => $position];

        $this->applyGraph($graph, $by);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function updateStep(string $key, array $config, ?User $by = null): void
    {
        $graph = $this->currentGraph();
        $found = false;

        foreach ($graph['steps'] as &$step) {
            if ($step['key'] === $key) {
                $step['config'] = [...($step['config'] ?? []), ...$config];
                $found = true;
                break;
            }
        }
        unset($step);

        if (! $found) {
            throw new InvalidArgumentException("Step [{$key}] was not found.");
        }

        $this->applyGraph($graph, $by);
    }

    public function removeStep(string $key, ?User $by = null): void
    {
        $graph = $this->currentGraph();

        $graph['steps'] = array_values(array_filter($graph['steps'], fn (array $step): bool => $step['key'] !== $key));
        $graph['edges'] = array_values(array_filter(
            $graph['edges'],
            fn (array $edge): bool => $edge['from'] !== $key && $edge['to'] !== $key,
        ));

        $this->applyGraph($graph, $by);
    }

    public function connect(string $from, string $to, ?string $condition = null, ?User $by = null): void
    {
        $graph = $this->currentGraph();
        $keys = collect($graph['steps'])->pluck('key');

        if (! $keys->contains($from) || ! $keys->contains($to)) {
            throw new InvalidArgumentException('Both steps must exist in the draft before they can be connected.');
        }

        $graph['edges'][] = ['from' => $from, 'to' => $to, 'condition' => $condition];

        $this->applyGraph($graph, $by);
    }

    public function disconnect(string $from, string $to, ?User $by = null): void
    {
        $graph = $this->currentGraph();

        $graph['edges'] = array_values(array_filter(
            $graph['edges'],
            fn (array $edge): bool => ! ($edge['from'] === $from && $edge['to'] === $to),
        ));

        $this->applyGraph($graph, $by);
    }

    /**
     * @return array{steps: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    private function currentGraph(): array
    {
        return $this->draft_graph ?? ['steps' => [], 'edges' => []];
    }

    /**
     * Persist a mutated graph as both the live draft and a versioned snapshot, so every
     * agent-driven edit is individually diffable/undoable via draftVersions().
     *
     * @param  array{steps: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $graph
     */
    private function applyGraph(array $graph, ?User $by): void
    {
        $this->draftVersions()->create([
            'triggered_by' => $by?->id,
            'graph_snapshot' => $graph,
        ]);

        $this->update([
            'draft_graph' => $graph,
            'draft_lock_version' => $this->draft_lock_version + 1,
        ]);
    }
}
