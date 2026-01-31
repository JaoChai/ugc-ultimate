<?php

namespace App\Jobs\Pipeline;

use App\Models\ApiKey;
use App\Models\Pipeline;
use App\Services\Pipeline\MusicVideoPipelineOrchestratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunMusicVideoPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900; // 15 minutes (longer for music generation + image generation)
    public int $tries = 1; // Don't retry automatically - pipeline can be resumed manually

    public function __construct(
        public int $pipelineId,
        public int $openRouterKeyId,
        public int $kieKeyId
    ) {}

    public function handle(MusicVideoPipelineOrchestratorService $orchestrator): void
    {
        Log::info('RunMusicVideoPipelineJob: Starting', [
            'pipeline_id' => $this->pipelineId,
        ]);

        $pipeline = Pipeline::find($this->pipelineId);
        if (!$pipeline) {
            Log::error('RunMusicVideoPipelineJob: Pipeline not found', [
                'pipeline_id' => $this->pipelineId,
            ]);
            return;
        }

        $openRouterKey = ApiKey::find($this->openRouterKeyId);
        $kieKey = ApiKey::find($this->kieKeyId);

        if (!$openRouterKey || !$kieKey) {
            Log::error('RunMusicVideoPipelineJob: API keys not found', [
                'pipeline_id' => $this->pipelineId,
                'openrouter_key_id' => $this->openRouterKeyId,
                'kie_key_id' => $this->kieKeyId,
            ]);

            $pipeline->update([
                'status' => Pipeline::STATUS_FAILED,
                'error_message' => 'Required API keys not found',
            ]);
            return;
        }

        $orchestrator->setOpenRouterKey($openRouterKey);
        $orchestrator->setKieApiKey($kieKey);

        try {
            $results = $orchestrator->runAutoMode($pipeline);

            Log::info('RunMusicVideoPipelineJob: Completed successfully', [
                'pipeline_id' => $this->pipelineId,
                'video_url' => $results['video']['video_url'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('RunMusicVideoPipelineJob: Failed', [
                'pipeline_id' => $this->pipelineId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $pipeline->update([
                'status' => Pipeline::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunMusicVideoPipelineJob: Job failed', [
            'pipeline_id' => $this->pipelineId,
            'error' => $exception->getMessage(),
        ]);

        $pipeline = Pipeline::find($this->pipelineId);
        if ($pipeline) {
            $pipeline->update([
                'status' => Pipeline::STATUS_FAILED,
                'error_message' => 'Job failed: ' . $exception->getMessage(),
            ]);
        }
    }
}
