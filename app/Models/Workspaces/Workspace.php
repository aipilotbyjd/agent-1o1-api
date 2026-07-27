<?php

namespace App\Models\Workspaces;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentKnowledge;
use App\Models\Agents\AgentMemory;
use App\Models\Agents\AgentSkill;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Credentials\Credential;
use App\Models\Nodes\Node;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationPreference;
use App\Models\Runs\Run;
use App\Models\Runs\RunLog;
use App\Models\Runs\RunReplayPack;
use App\Models\Tool;
use App\Models\User;
use App\Models\Variable;
use App\Models\Workflows\GitSyncConfig;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowApproval;
use App\Models\Workflows\WorkflowBuilderSession;
use Database\Factories\Workspaces\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'avatar', 'owner_id'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<WorkspaceInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * @return HasMany<NotificationChannel, $this>
     */
    public function notificationChannels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class);
    }

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * @return HasMany<Tool, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }

    /**
     * @return HasMany<Workflow, $this>
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    /**
     * @return HasMany<Run, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    /**
     * Custom nodes owned by this workspace (excludes the shared builtin catalog).
     *
     * @return HasMany<Node, $this>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    /**
     * @return HasMany<Credential, $this>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    /**
     * @return HasMany<Variable, $this>
     */
    public function variables(): HasMany
    {
        return $this->hasMany(Variable::class);
    }

    /**
     * @return HasMany<AgentKnowledge, $this>
     */
    public function agentKnowledge(): HasMany
    {
        return $this->hasMany(AgentKnowledge::class);
    }

    /**
     * @return HasMany<AgentMemory, $this>
     */
    public function agentMemories(): HasMany
    {
        return $this->hasMany(AgentMemory::class);
    }

    /**
     * @return HasMany<DocumentEmbedding, $this>
     */
    public function documentEmbeddings(): HasMany
    {
        return $this->hasMany(DocumentEmbedding::class);
    }

    /**
     * @return HasMany<AgentSkill, $this>
     */
    public function agentSkills(): HasMany
    {
        return $this->hasMany(AgentSkill::class);
    }

    /**
     * @return HasMany<RunLog, $this>
     */
    public function runLogs(): HasMany
    {
        return $this->hasMany(RunLog::class);
    }

    /**
     * @return HasMany<RunReplayPack, $this>
     */
    public function runReplayPacks(): HasMany
    {
        return $this->hasMany(RunReplayPack::class);
    }

    /**
     * @return HasMany<WorkspaceEnvironment, $this>
     */
    public function environments(): HasMany
    {
        return $this->hasMany(WorkspaceEnvironment::class);
    }

    /**
     * @return HasMany<WorkflowApproval, $this>
     */
    public function workflowApprovals(): HasMany
    {
        return $this->hasMany(WorkflowApproval::class);
    }

    /**
     * @return HasMany<GitSyncConfig, $this>
     */
    public function gitSyncConfigs(): HasMany
    {
        return $this->hasMany(GitSyncConfig::class);
    }

    /**
     * @return HasMany<WorkflowBuilderSession, $this>
     */
    public function builderSessions(): HasMany
    {
        return $this->hasMany(WorkflowBuilderSession::class);
    }
}
