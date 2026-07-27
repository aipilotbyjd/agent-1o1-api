<?php

namespace App\Http\Resources\V1\Runs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunReplayPackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'workflow_id' => $this->workflow_id,
            'run_id' => $this->run_id,
            'created_by' => $this->created_by,
            'label' => $this->label,
            'version_snapshot' => $this->version_snapshot,
            'trigger_data' => $this->trigger_data,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
