<?php

namespace App\Models\Workflows;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Workflows\GitSyncConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'created_by', 'provider', 'repository', 'branch', 'base_path',
    'access_token', 'webhook_secret', 'is_active', 'last_synced_at',
])]
class GitSyncConfig extends Model
{
    /** @use HasFactory<GitSyncConfigFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'provider' => 'github',
        'branch' => 'main',
        'base_path' => 'workflows',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
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
}
