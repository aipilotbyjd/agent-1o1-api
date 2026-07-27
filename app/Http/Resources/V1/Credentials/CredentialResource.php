<?php

namespace App\Http\Resources\V1\Credentials;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CredentialResource extends JsonResource
{
    /**
     * Transform the resource into an array. Deliberately never includes `data` —
     * decrypted secrets must never leave the server once stored.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'type' => $this->type,
            'is_expired' => $this->isExpired(),
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
