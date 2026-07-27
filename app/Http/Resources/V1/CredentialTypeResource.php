<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CredentialTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'auth_type' => $this->auth_type,
            'color' => $this->color,
            'icon' => $this->icon,
            'docs_url' => $this->docs_url,
            'fields' => $this->fields,
        ];
    }
}
