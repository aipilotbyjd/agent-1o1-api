<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GitSyncConfigResource extends JsonResource
{
    /**
     * Redacts access_token/webhook_secret — only their presence is exposed, never the value.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'created_by' => $this->created_by,
            'provider' => $this->provider,
            'repository' => $this->repository,
            'branch' => $this->branch,
            'base_path' => $this->base_path,
            'has_access_token' => $this->access_token !== null,
            'has_webhook_secret' => $this->webhook_secret !== null,
            'is_active' => $this->is_active,
            'last_synced_at' => $this->last_synced_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
