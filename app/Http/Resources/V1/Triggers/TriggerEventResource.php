<?php

namespace App\Http\Resources\V1\Triggers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TriggerEventResource extends JsonResource
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
            'trigger_id' => $this->trigger_id,
            'source' => $this->source,
            'status' => $this->status,
            'matched' => $this->matched,
            'run_id' => $this->run_id,
            'payload_snippet' => $this->payload_snippet,
            'headers' => $this->headers,
            'error' => $this->error,
            'attempts' => $this->attempts,
            'duplicate_count' => $this->duplicate_count,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
        ];
    }
}
