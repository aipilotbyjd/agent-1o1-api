<?php

namespace App\Models\Runs;

use Database\Factories\Runs\ArchivedRunLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['run_id', 'workspace_id', 'step_key', 'level', 'message', 'context', 'logged_at', 'archived_at'])]
class ArchivedRunLog extends Model
{
    /** @use HasFactory<ArchivedRunLogFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'logged_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
