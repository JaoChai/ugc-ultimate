<?php

namespace App\Events;

use App\Models\Pipeline;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipelineProgressEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Pipeline $pipeline,
        public string $step,
        public int $progress,
        public string $status,
        public ?string $message = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('pipeline.' . $this->pipeline->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pipeline.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'pipeline_id' => $this->pipeline->id,
            'step' => $this->step,
            'progress' => $this->progress,
            'status' => $this->status,
            'message' => $this->message,
            'timestamp' => now()->toISOString(),
        ];
    }
}
