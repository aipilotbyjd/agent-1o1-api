<?php

namespace App\Events;

use App\Models\Runs\Run;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RunUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Run $run) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("workspace.{$this->run->workspace_id}");
    }

    public function broadcastAs(): string
    {
        return 'run.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->run->id,
            'status' => $this->run->status->value,
            'output' => $this->run->output,
            'error' => $this->run->error,
            'started_at' => $this->run->started_at,
            'finished_at' => $this->run->finished_at,
        ];
    }
}
