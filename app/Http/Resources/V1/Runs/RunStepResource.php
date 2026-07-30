<?php

namespace App\Http\Resources\V1\Runs;

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

            // Only present while a wait step is parked — this is the URL the external
            // system posts to in order to resume the run, so it disappears once used.
            'callback_url' => $this->when(
                $this->callback_token !== null,
                fn (): string => route('v1.run-callbacks.resume', ['token' => $this->callback_token]),
            ),
            'callback_expires_at' => $this->when(
                $this->callback_token !== null,
                fn () => $this->callback_expires_at,
            ),
        ];
    }
}
