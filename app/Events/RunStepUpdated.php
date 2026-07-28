<?php

namespace App\Events;

use App\Models\Runs\RunStep;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RunStepUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public RunStep $step) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("workspace.{$this->step->run->workspace_id}");
    }

    public function broadcastAs(): string
    {
        return 'run-step.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->step->id,
            'run_id' => $this->step->run_id,
            'key' => $this->step->key,
            'type' => $this->step->type,
            'status' => $this->step->status->value,
            'output' => $this->step->output,
            'error' => $this->step->error,
            'usage' => $this->step->usage,
        ];
    }
}
