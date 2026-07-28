<?php

namespace App\Http\Resources\V1\Workspaces;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiGenerationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'created_by' => $this->created_by,
            'type' => $this->type,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_summary' => $this->prompt_summary,
            'tokens_used' => $this->tokens_used,
            'created_at' => $this->created_at,
        ];
    }
}
