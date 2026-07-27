<?php

namespace App\Models\Credentials;

use App\Models\Tool;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Credentials\CredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workspace_id', 'created_by', 'name', 'type', 'data', 'last_used_at', 'expires_at'])]
class Credential extends Model
{
    /** @use HasFactory<CredentialFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Canonical `type` values (matching credential_types.key) that HttpToolHandler
     * knows how to apply to an outbound request.
     */
    public const TYPE_API_KEY = 'api_key';

    public const TYPE_BEARER_TOKEN = 'bearer_token';

    public const TYPE_BASIC_AUTH = 'basic_auth';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'encrypted:array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * @return HasMany<Tool, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
