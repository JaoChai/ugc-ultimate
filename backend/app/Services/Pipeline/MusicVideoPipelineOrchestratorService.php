<?php

namespace App\Services\Pipeline;

use App\Events\PipelineProgressEvent;
use App\Events\PipelineStepCompletedEvent;
use App\Models\AgentConfig;
use App\Models\ApiKey;
use App\Models\Pipeline;
use App\Models\PipelineLog;
use App\Services\FFMpegService;
use App\Services\OpenRouterService;
use App\Services\Pipeline\Agents\SongArchitectService;
use App\Services\Pipeline\Agents\SongSelectorService;
use App\Services\Pipeline\Agents\SunoExpertService;
use App\Services\Pipeline\Agents\VisualDesignerService;

class MusicVideoPipelineOrchestratorService
{
    protected OpenRouterService $openRouter;
    protected SongArchitectService $songArchitect;
    protected SunoExpertService $sunoExpert;
    protected SongSelectorService $songSelector;
    protected VisualDesignerService $visualDesigner;
    protected FFMpegService $ffmpeg;

    protected ?ApiKey $openRouterKey = null;
    protected ?ApiKey $kieApiKey = null;

    public function __construct(
        OpenRouterService $openRouter,
        SongArchitectService $songArchitect,
        SunoExpertService $sunoExpert,
        SongSelectorService $songSelector,
        VisualDesignerService $visualDesigner,
        FFMpegService $ffmpeg
    ) {
        $this->openRouter = $openRouter;
        $this->songArchitect = $songArchitect;
        $this->sunoExpert = $sunoExpert;
        $this->songSelector = $songSelector;
        $this->visualDesigner = $visualDesigner;
        $this->ffmpeg = $ffmpeg;
    }

    /**
     * Set the OpenRouter API key
     */
    public function setOpenRouterKey(ApiKey $apiKey): self
    {
        $this->openRouterKey = $apiKey;
        $this->openRouter->setApiKey($apiKey);
        return $this;
    }

    /**
     * Set the Kie API key (for Suno and Nano Banana)
     */
    public function setKieApiKey(ApiKey $apiKey): self
    {
        $this->kieApiKey = $apiKey;
        $this->sunoExpert->setKieApiKey($apiKey);
        $this->visualDesigner->setKieApiKey($apiKey);
        return $this;
    }

    /**
     * Run the complete music video pipeline in auto mode
     */
    public function runAutoMode(Pipeline $pipeline): array
    {
        $this->startPipeline($pipeline);

        $results = [];

        try {
            // Step 1: Song Architect - Design song structure, lyrics, hook, title
            $results['song_architect'] = $this->runStep($pipeline, AgentConfig::TYPE_SONG_ARCHITECT);

            // Step 2: Suno Expert - Optimize for Suno + Generate music
            $results['suno_expert'] = $this->runStep($pipeline, AgentConfig::TYPE_SUNO_EXPERT, [
                'song_concept' => $results['song_architect'],
            ]);

            // Step 3: Song Selector - Select best version from Suno
            $results['song_selector'] = $this->runStep($pipeline, AgentConfig::TYPE_SONG_SELECTOR, [
                'song_concept' => $results['song_architect'],
                'suno_result' => $results['suno_expert'],
            ]);

            // Step 4: Visual Designer - Create image concept + Generate with Nano Banana
            $results['visual_designer'] = $this->runStep($pipeline, AgentConfig::TYPE_VISUAL_DESIGNER, [
                'hook' => $results['song_architect']['hook'] ?? '',
                'song_title' => $results['song_architect']['song_title'] ?? '',
                'mood' => $results['song_architect']['mood'] ?? '',
                'genre' => $results['song_architect']['genre'] ?? '',
                'selected_audio_url' => $results['song_selector']['selected_audio_url'] ?? null,
            ]);

            // Step 5: Compose Video (FFmpeg) - Static image + Audio
            PipelineLog::info($pipeline, 'orchestrator', 'Starting video composition...');

            $videoResult = $this->composeSimpleVideo(
                $results['visual_designer']['image_url'] ?? null,
                $results['song_selector']['selected_audio_url'] ?? null
            );

            $results['video'] = $videoResult;

            $this->completePipeline($pipeline, $results);

        } catch (\Exception $e) {
            $this->failPipeline($pipeline, $e->getMessage());
            throw $e;
        }

        return $results;
    }

    /**
     * Run a single step
     */
    public function runStep(Pipeline $pipeline, string $step, array $additionalInput = []): array
    {
        // Update pipeline state
        $pipeline->update([
            'current_step' => $step,
            'current_step_progress' => 0,
            'status' => Pipeline::STATUS_RUNNING,
        ]);

        $pipeline->updateStepState($step, [
            'status' => 'running',
            'started_at' => now()->toISOString(),
        ]);

        // Broadcast progress
        broadcast(new PipelineProgressEvent(
            $pipeline,
            $step,
            0,
            'running',
            "Starting {$step}..."
        ))->toOthers();

        try {
            // Get the agent service
            $agent = $this->getAgentService($step);

            // Configure the agent
            $agent->setPipeline($pipeline);

            // Build input
            $input = $this->buildStepInput($pipeline, $step, $additionalInput);

            // Execute
            $result = $agent->execute($input);

            // Update step state
            $pipeline->updateStepState($step, [
                'status' => 'completed',
                'progress' => 100,
                'result' => $result,
                'completed_at' => now()->toISOString(),
            ]);

            // Broadcast completion
            broadcast(new PipelineStepCompletedEvent(
                $pipeline,
                $step,
                $result,
                $pipeline->getNextStep()
            ))->toOthers();

            return $result;

        } catch (\Exception $e) {
            $pipeline->updateStepState($step, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now()->toISOString(),
            ]);

            PipelineLog::error($pipeline, $step, $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the agent service for a step
     */
    protected function getAgentService(string $step): Agents\BaseAgentService
    {
        return match ($step) {
            AgentConfig::TYPE_SONG_ARCHITECT => $this->songArchitect,
            AgentConfig::TYPE_SUNO_EXPERT => $this->sunoExpert,
            AgentConfig::TYPE_SONG_SELECTOR => $this->songSelector,
            AgentConfig::TYPE_VISUAL_DESIGNER => $this->visualDesigner,
            default => throw new \InvalidArgumentException("Unknown step: {$step}"),
        };
    }

    /**
     * Build input for a step
     */
    protected function buildStepInput(Pipeline $pipeline, string $step, array $additionalInput = []): array
    {
        $config = $pipeline->config ?? [];

        $baseInput = [
            'song_brief' => $config['song_brief'] ?? $config['theme'] ?? '',
        ];

        return array_merge($baseInput, $additionalInput);
    }

    /**
     * Compose simple video (static image + audio)
     */
    protected function composeSimpleVideo(?string $imageUrl, ?string $audioUrl): array
    {
        if (empty($imageUrl)) {
            throw new \RuntimeException('No image URL available for video composition');
        }

        if (empty($audioUrl)) {
            throw new \RuntimeException('No audio URL available for video composition');
        }

        return $this->ffmpeg->composeSimpleVideo($imageUrl, $audioUrl);
    }

    /**
     * Start the pipeline
     */
    protected function startPipeline(Pipeline $pipeline): void
    {
        $steps = Pipeline::MUSIC_VIDEO_STEPS;

        $pipeline->update([
            'status' => Pipeline::STATUS_RUNNING,
            'started_at' => now(),
            'current_step' => $steps[0],
            'current_step_progress' => 0,
        ]);

        PipelineLog::info($pipeline, 'orchestrator', 'Music Video Pipeline started in auto mode');
    }

    /**
     * Complete the pipeline
     */
    protected function completePipeline(Pipeline $pipeline, array $results): void
    {
        $pipeline->update([
            'status' => Pipeline::STATUS_COMPLETED,
            'completed_at' => now(),
            'current_step' => null,
            'current_step_progress' => 100,
        ]);

        PipelineLog::result($pipeline, 'orchestrator', 'Music Video Pipeline completed successfully', [
            'steps_completed' => count($results),
            'video_url' => $results['video']['video_url'] ?? null,
        ]);

        broadcast(new PipelineProgressEvent(
            $pipeline,
            'orchestrator',
            100,
            'completed',
            'Music Video Pipeline completed successfully!'
        ))->toOthers();
    }

    /**
     * Fail the pipeline
     */
    protected function failPipeline(Pipeline $pipeline, string $error): void
    {
        $pipeline->update([
            'status' => Pipeline::STATUS_FAILED,
            'error_message' => $error,
        ]);

        PipelineLog::error($pipeline, 'orchestrator', 'Music Video Pipeline failed: ' . $error);

        broadcast(new PipelineProgressEvent(
            $pipeline,
            'orchestrator',
            0,
            'failed',
            $error
        ))->toOthers();
    }
}
