<?php

namespace App\Http\Resources\V1\Runs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'runnable_type' => $this->runnable_type,
            'runnable_id' => $this->runnable_id,
            'workflow_version' => $this->whenLoaded('workflowVersion', fn (): ?int => $this->workflowVersion?->version),
            'agent_version' => $this->agent_version,
            'status' => $this->status,
            'trigger_type' => $this->trigger_type,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'triggered_by' => $this->triggered_by,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'created_at' => $this->created_at,
            'steps' => RunStepResource::collection($this->whenLoaded('steps')),
        ];
    }
}
