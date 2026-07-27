<?php

namespace App\Models;

use App\Models\Workspaces\Workspace;
use Database\Factories\VariableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'created_by', 'key', 'value', 'is_secret'])]
class Variable extends Model
{
    /** @use HasFactory<VariableFactory> */
    use HasFactory;

    /**
     * Stored encrypted regardless of is_secret — that flag only controls whether the
     * value is redacted in API responses, not whether it is encrypted at rest.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'is_secret' => 'boolean',
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
