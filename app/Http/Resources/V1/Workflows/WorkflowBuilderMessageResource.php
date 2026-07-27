<?php

namespace App\Http\Resources\V1\Workflows;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowBuilderMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'draft_version_id' => $this->draft_version_id,
            'role' => $this->role,
            'content' => $this->content,
            'actions' => $this->actions,
            'processing_status' => $this->processing_status,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
