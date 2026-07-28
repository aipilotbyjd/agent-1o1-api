<?php

namespace App\Http\Resources\V1\Agents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentEvalRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'suite_id' => $this->suite_id,
            'agent_id' => $this->agent_id,
            'triggered_by' => $this->triggered_by,
            'status' => $this->status,
            'total' => $this->total,
            'passed' => $this->passed,
            'failed' => $this->failed,
            'results' => $this->results,
            'error' => $this->error,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
        ];
    }
}
