<?php

namespace App\Events;

use App\Models\Pipeline;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipelineStepCompletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Pipeline $pipeline,
        public string $step,
        public array $result,
        public ?string $nextStep = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('pipeline.' . $this->pipeline->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pipeline.step.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'pipeline_id' => $this->pipeline->id,
            'step' => $this->step,
            'result' => $this->result,
            'next_step' => $this->nextStep,
            'timestamp' => now()->toISOString(),
        ];
    }
}
