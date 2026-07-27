<?php

namespace App\Http\Resources\V1\Runs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_id' => $this->run_id,
            'step_key' => $this->step_key,
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'logged_at' => $this->logged_at,
        ];
    }
}
