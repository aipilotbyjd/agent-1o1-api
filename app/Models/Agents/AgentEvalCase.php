<?php

namespace App\Models\Agents;

use Database\Factories\Agents\AgentEvalCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['suite_id', 'name', 'input', 'assertions', 'sort_order'])]
class AgentEvalCase extends Model
{
    /** @use HasFactory<AgentEvalCaseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assertions' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AgentEvalSuite, $this>
     */
    public function suite(): BelongsTo
    {
        return $this->belongsTo(AgentEvalSuite::class, 'suite_id');
    }
}
