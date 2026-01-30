<?php

namespace App\Jobs\Pipeline;

use App\Models\ApiKey;
use App\Models\Pipeline;
use App\Services\Pipeline\PipelineOrchestratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes

    public function __construct(
        public int $pipelineId,
        public int $openRouterKeyId,
        public int $kieKeyId
    ) {}

    public function handle(PipelineOrchestratorService $orchestrator): void
    {
        $pipeline = Pipeline::find($this->pipelineId);
        if (!$pipeline) {
            return;
        }

        $openRouterKey = ApiKey::find($this->openRouterKeyId);
        $kieKey = ApiKey::find($this->kieKeyId);

        if (!$openRouterKey || !$kieKey) {
            $pipeline->update([
                'status' => Pipeline::STATUS_FAILED,
                'error_message' => 'Required API keys not found',
            ]);
            return;
        }

        $orchestrator->setOpenRouterKey($openRouterKey);
        $orchestrator->setKieApiKey($kieKey);

        try {
            $orchestrator->runAutoMode($pipeline);
        } catch (\Exception $e) {
            $pipeline->update([
                'status' => Pipeline::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $pipeline = Pipeline::find($this->pipelineId);
        if ($pipeline) {
            $pipeline->update([
                'status' => Pipeline::STATUS_FAILED,
                'error_message' => 'Job failed: ' . $exception->getMessage(),
            ]);
        }
    }
}
