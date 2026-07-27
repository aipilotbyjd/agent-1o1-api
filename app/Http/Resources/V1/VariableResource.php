<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariableResource extends JsonResource
{
    /**
     * Transform the resource into an array. Secret values are redacted, not just hidden,
     * so the API shape stays consistent whether or not a variable is secret.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'key' => $this->key,
            'value' => $this->is_secret ? null : $this->value,
            'is_secret' => $this->is_secret,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
