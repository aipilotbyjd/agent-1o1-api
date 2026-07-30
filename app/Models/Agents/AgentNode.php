<?php

namespace App\Models\Agents;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One node attached to one agent as a callable tool.
 *
 * The pivot is where a catalog node stops being generic: `config` fixes the values the
 * model must not choose (the credential, the Slack channel), and `exposed_fields`
 * narrows what is left to the arguments worth asking a model to fill.
 */
class AgentNode extends Pivot
{
    protected $table = 'agent_node';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'exposed_fields' => 'array',
        ];
    }
}
