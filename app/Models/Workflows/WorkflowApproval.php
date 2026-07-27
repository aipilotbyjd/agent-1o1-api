<?php

namespace App\Models\Workflows;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Workflows\WorkflowApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'workflow_id', 'version_id', 'requested_by',
    'reviewed_by', 'status', 'notes', 'reviewed_at',
])]
class WorkflowApproval extends Model
{
    /** @use HasFactory<WorkflowApprovalFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Approve the request. When it was raised against the current draft rather than an
     * already-published version (`version_id` null — "please review and publish this"),
     * approving it publishes that draft and records the resulting version.
     */
    public function approve(User $reviewer, ?string $notes = null): void
    {
        $versionId = $this->version_id ?? $this->workflow->publishVersion($reviewer, $notes)->id;

        $this->update([
            'status' => 'approved',
            'version_id' => $versionId,
            'reviewed_by' => $reviewer->id,
            'notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }

    public function reject(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }
}
