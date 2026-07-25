<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunStepResource extends JsonResource
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
            'key' => $this->key,
            'type' => $this->type,
            'status' => $this->status,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'usage' => $this->usage,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
        ];
    }
}
