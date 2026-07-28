<?php

namespace App\Http\Resources\V1\Runs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunFixSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_id' => $this->run_id,
            'workspace_id' => $this->workspace_id,
            'step_key' => $this->step_key,
            'step_type' => $this->step_type,
            'diagnosis' => $this->diagnosis,
            'suggestions' => $this->suggestions,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
