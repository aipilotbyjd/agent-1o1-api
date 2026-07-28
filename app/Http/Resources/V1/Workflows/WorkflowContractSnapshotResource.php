<?php

namespace App\Http\Resources\V1\Workflows;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowContractSnapshotResource extends JsonResource
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
            'created_by' => $this->created_by,
            'input_schema' => $this->input_schema,
            'output_schema' => $this->output_schema,
            'node_signature' => $this->node_signature,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
