<?php

namespace App\Http\Resources\V1\Triggers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TriggerResource extends JsonResource
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
            'type' => $this->type,
            'trigger_type' => $this->whenLoaded('triggerType', fn (): array => [
                'key' => $this->triggerType->key,
                'name' => $this->triggerType->name,
                'category' => $this->triggerType->category,
            ]),
            'config' => $this->config,
            'token' => $this->token,
            'webhook_url' => $this->when($this->type === 'webhook' && $this->token !== null,
                fn (): string => url("/api/v1/hooks/{$this->token}"),
            ),
            'has_signing_secret' => $this->hasSigningSecret(),
            'credential_id' => $this->credential_id,
            'consecutive_failure_count' => $this->consecutive_failure_count,
            'is_active' => $this->is_active,
            'last_run_at' => $this->last_run_at,
            'created_at' => $this->created_at,
        ];
    }
}
