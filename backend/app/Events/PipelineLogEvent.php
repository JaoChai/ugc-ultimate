<?php

namespace App\Events;

use App\Models\Pipeline;
use App\Models\PipelineLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipelineLogEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Pipeline $pipeline,
        public string $agentType,
        public string $logType,
        public string $message,
        public ?array $data = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('pipeline.' . $this->pipeline->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pipeline.log';
    }

    public function broadcastWith(): array
    {
        return [
            'pipeline_id' => $this->pipeline->id,
            'agent_type' => $this->agentType,
            'log_type' => $this->logType,
            'message' => $this->message,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
