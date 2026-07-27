<?php

namespace App\Models\Workflows;

use App\Models\Nodes\Node;
use App\Models\Runs\Run;
use App\Models\Runs\RunReplayPack;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Workflows\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'workspace_id', 'name', 'slug', 'description', 'status',
    'current_version_id', 'has_unpublished_changes', 'created_by',
])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'has_unpublished_changes' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_unpublished_changes' => 'boolean',
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
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class);
    }

    /**
     * @return HasMany<WorkflowStepEdge, $this>
     */
    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowStepEdge::class);
    }

    /**
     * @return HasMany<WorkflowVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'current_version_id');
    }

    /**
     * @return MorphMany<Run, $this>
     */
    public function runs(): MorphMany
    {
        return $this->morphMany(Run::class, 'runnable');
    }

    /**
     * @return HasMany<RunReplayPack, $this>
     */
    public function replayPacks(): HasMany
    {
        return $this->hasMany(RunReplayPack::class);
    }

    /**
     * @return HasMany<WorkflowApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(WorkflowApproval::class);
    }

    /**
     * @return HasMany<WorkflowEnvironmentRelease, $this>
     */
    public function environmentReleases(): HasMany
    {
        return $this->hasMany(WorkflowEnvironmentRelease::class);
    }

    /**
     * @return HasMany<WorkflowContractSnapshot, $this>
     */
    public function contractSnapshots(): HasMany
    {
        return $this->hasMany(WorkflowContractSnapshot::class);
    }

    /**
     * @return HasMany<WorkflowShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(WorkflowShare::class);
    }

    /**
     * @return HasMany<WorkflowBuilderSession, $this>
     */
    public function builderSessions(): HasMany
    {
        return $this->hasMany(WorkflowBuilderSession::class);
    }

    /**
     * Serialize the live (draft) steps and edges into a graph array.
     *
     * @return array{steps: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function graphArray(): array
    {
        $steps = $this->steps()->get();
        $stepKeysById = $steps->pluck('key', 'id');

        return [
            'steps' => $steps->map(fn (WorkflowStep $step): array => [
                'key' => $step->key,
                'type' => $step->type->value,
                'config' => $step->config ?? [],
                'position' => $step->position,
            ])->all(),
            'edges' => $this->edges()->get()->map(fn (WorkflowStepEdge $edge): array => [
                'from' => $stepKeysById[$edge->from_step_id],
                'to' => $stepKeysById[$edge->to_step_id],
                'condition' => $edge->condition,
            ])->all(),
        ];
    }

    /**
     * Replace the live draft graph with the given steps and edges.
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $edges
     */
    public function replaceGraph(array $steps, array $edges): void
    {
        DB::transaction(function () use ($steps, $edges): void {
            $this->edges()->delete();
            $this->steps()->delete();

            $nodeIdsByStepType = Node::query()
                ->whereNull('workspace_id')
                ->where('is_custom', false)
                ->pluck('id', 'step_type');

            $stepsByKey = [];

            foreach ($steps as $stepData) {
                $stepsByKey[$stepData['key']] = $this->steps()->create([
                    'key' => $stepData['key'],
                    'type' => $stepData['type'],
                    'node_id' => $nodeIdsByStepType[$stepData['type']] ?? null,
                    'config' => $stepData['config'] ?? [],
                    'position' => $stepData['position'] ?? null,
                ]);
            }

            foreach ($edges as $edgeData) {
                $this->edges()->create([
                    'from_step_id' => $stepsByKey[$edgeData['from']]->id,
                    'to_step_id' => $stepsByKey[$edgeData['to']]->id,
                    'condition' => $edgeData['condition'] ?? null,
                ]);
            }

            $this->update(['has_unpublished_changes' => $this->current_version_id !== null]);
        });
    }

    /**
     * Snapshot the live graph into an immutable version and make it current.
     */
    public function publishVersion(?User $publishedBy = null, ?string $notes = null): WorkflowVersion
    {
        return DB::transaction(function () use ($publishedBy, $notes): WorkflowVersion {
            $version = $this->versions()->create([
                'version' => ((int) $this->versions()->max('version')) + 1,
                'graph' => $this->graphArray(),
                'notes' => $notes,
                'published_by' => $publishedBy?->id,
            ]);

            $this->update([
                'status' => 'published',
                'current_version_id' => $version->id,
                'has_unpublished_changes' => false,
            ]);

            return $version;
        });
    }
}
