<?php

namespace App\Http\Resources\V1\Workflows;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowBuilderSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'user_id' => $this->user_id,
            'workflow_id' => $this->workflow_id,
            'title' => $this->title,
            'draft_graph' => $this->draft_graph,
            'draft_lock_version' => $this->draft_lock_version,
            'status' => $this->status,
            'message_count' => $this->whenCounted('messages'),
            'last_activity_at' => $this->last_activity_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
