<?php

namespace App\Http\Resources\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
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
            'client_name' => $this->client?->name,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'is_current' => $request->user()?->token()?->id === $this->id,
        ];
    }
}
