<?php

namespace App\Http\Resources\V1\Workflows;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowApprovalResource extends JsonResource
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
            'version_id' => $this->version_id,
            'requested_by' => $this->requested_by,
            'reviewed_by' => $this->reviewed_by,
            'status' => $this->status,
            'notes' => $this->notes,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
