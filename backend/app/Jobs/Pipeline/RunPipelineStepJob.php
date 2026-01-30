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

class RunPipelineStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes per step

    public function __construct(
        public int $pipelineId,
        public string $step,
        public int $openRouterKeyId,
        public int $kieKeyId
    ) {}

    public function handle(PipelineOrchestratorService $orchestrator): void
    {
        $pipeline = Pipeline::find($this->pipelineId);
        if (!$pipeline) {
            return;
        }

        // Check if pipeline is still active
        if (!in_array($pipeline->status, [Pipeline::STATUS_PENDING, Pipeline::STATUS_RUNNING, Pipeline::STATUS_PAUSED])) {
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
            $result = $orchestrator->runStep($pipeline, $this->step);

            // If in auto mode and there's a next step, dispatch it
            if ($pipeline->mode === Pipeline::MODE_AUTO) {
                $nextStep = $pipeline->getNextStep();
                if ($nextStep) {
                    self::dispatch(
                        $this->pipelineId,
                        $nextStep,
                        $this->openRouterKeyId,
                        $this->kieKeyId
                    );
                } else {
                    // Pipeline completed
                    $pipeline->update([
                        'status' => Pipeline::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }
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
                'error_message' => "Step {$this->step} failed: " . $exception->getMessage(),
            ]);
        }
    }
}
