<?php

namespace App\Services\Pipeline;

use App\Events\PipelineProgressEvent;
use App\Events\PipelineStepCompletedEvent;
use App\Models\AgentConfig;
use App\Models\ApiKey;
use App\Models\Pipeline;
use App\Models\PipelineLog;
use App\Services\OpenRouterService;
use App\Services\Pipeline\Agents\ImageGeneratorService;
use App\Services\Pipeline\Agents\MusicComposerService;
use App\Services\Pipeline\Agents\ThemeDirectorService;
use App\Services\Pipeline\Agents\VideoComposerService;
use App\Services\Pipeline\Agents\VisualDirectorService;

class PipelineOrchestratorService
{
    protected OpenRouterService $openRouter;
    protected ThemeDirectorService $themeDirector;
    protected MusicComposerService $musicComposer;
    protected VisualDirectorService $visualDirector;
    protected ImageGeneratorService $imageGenerator;
    protected VideoComposerService $videoComposer;

    protected ?ApiKey $openRouterKey = null;
    protected ?ApiKey $kieApiKey = null;

    public function __construct(
        OpenRouterService $openRouter,
        ThemeDirectorService $themeDirector,
        MusicComposerService $musicComposer,
        VisualDirectorService $visualDirector,
        ImageGeneratorService $imageGenerator,
        VideoComposerService $videoComposer
    ) {
        $this->openRouter = $openRouter;
        $this->themeDirector = $themeDirector;
        $this->musicComposer = $musicComposer;
        $this->visualDirector = $visualDirector;
        $this->imageGenerator = $imageGenerator;
        $this->videoComposer = $videoComposer;
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
        $this->musicComposer->setKieApiKey($apiKey);
        $this->imageGenerator->setKieApiKey($apiKey);
        return $this;
    }

    /**
     * Run the complete pipeline in auto mode
     */
    public function runAutoMode(Pipeline $pipeline): array
    {
        $this->startPipeline($pipeline);

        $results = [];

        try {
            // Step 1: Theme Director
            $results['theme_director'] = $this->runStep($pipeline, AgentConfig::TYPE_THEME_DIRECTOR);

            // Step 2: Music Composer
            $results['music_composer'] = $this->runStep($pipeline, AgentConfig::TYPE_MUSIC_COMPOSER, [
                'theme_concept' => $results['theme_director'],
            ]);

            // Step 3: Visual Director
            $results['visual_director'] = $this->runStep($pipeline, AgentConfig::TYPE_VISUAL_DIRECTOR, [
                'theme_concept' => $results['theme_director'],
                'music_concept' => $results['music_composer'],
            ]);

            // Step 4: Image Generator
            $results['image_generator'] = $this->runStep($pipeline, AgentConfig::TYPE_IMAGE_GENERATOR, [
                'scenes' => $results['visual_director']['scenes'] ?? [],
                'style_guide' => $results['visual_director']['style_guide'] ?? [],
            ]);

            // Step 5: Video Composer
            $results['video_composer'] = $this->runStep($pipeline, AgentConfig::TYPE_VIDEO_COMPOSER, [
                'images' => $results['image_generator']['images'] ?? [],
                'music_url' => $results['music_composer']['audio_url'] ?? null,
                'scenes' => $results['visual_director']['scenes'] ?? [],
                'duration' => $pipeline->config['duration'] ?? 60,
            ]);

            $this->completePipeline($pipeline, $results);

        } catch (\Exception $e) {
            $this->failPipeline($pipeline, $e->getMessage());
            throw $e;
        }

        return $results;
    }

    /**
     * Run a single step in manual mode
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
            AgentConfig::TYPE_THEME_DIRECTOR => $this->themeDirector,
            AgentConfig::TYPE_MUSIC_COMPOSER => $this->musicComposer,
            AgentConfig::TYPE_VISUAL_DIRECTOR => $this->visualDirector,
            AgentConfig::TYPE_IMAGE_GENERATOR => $this->imageGenerator,
            AgentConfig::TYPE_VIDEO_COMPOSER => $this->videoComposer,
            default => throw new \InvalidArgumentException("Unknown step: {$step}"),
        };
    }

    /**
     * Build input for a step
     */
    protected function buildStepInput(Pipeline $pipeline, string $step, array $additionalInput = []): array
    {
        $config = $pipeline->config ?? [];
        $stepsState = $pipeline->steps_state ?? [];

        $baseInput = [
            'theme' => $config['theme'] ?? '',
            'duration' => $config['duration'] ?? 60,
            'platform' => $config['platform'] ?? 'youtube',
        ];

        // Add results from previous steps
        if ($step !== AgentConfig::TYPE_THEME_DIRECTOR) {
            $baseInput['theme_concept'] = $stepsState[AgentConfig::TYPE_THEME_DIRECTOR]['result'] ?? [];
        }

        if (in_array($step, [AgentConfig::TYPE_VISUAL_DIRECTOR, AgentConfig::TYPE_IMAGE_GENERATOR, AgentConfig::TYPE_VIDEO_COMPOSER])) {
            $baseInput['music_concept'] = $stepsState[AgentConfig::TYPE_MUSIC_COMPOSER]['result'] ?? [];
        }

        if (in_array($step, [AgentConfig::TYPE_IMAGE_GENERATOR, AgentConfig::TYPE_VIDEO_COMPOSER])) {
            $visualResult = $stepsState[AgentConfig::TYPE_VISUAL_DIRECTOR]['result'] ?? [];
            $baseInput['scenes'] = $visualResult['scenes'] ?? [];
            $baseInput['style_guide'] = $visualResult['style_guide'] ?? [];
        }

        if ($step === AgentConfig::TYPE_VIDEO_COMPOSER) {
            $imageResult = $stepsState[AgentConfig::TYPE_IMAGE_GENERATOR]['result'] ?? [];
            $musicResult = $stepsState[AgentConfig::TYPE_MUSIC_COMPOSER]['result'] ?? [];
            $baseInput['images'] = $imageResult['images'] ?? [];
            $baseInput['music_url'] = $musicResult['audio_url'] ?? null;
        }

        return array_merge($baseInput, $additionalInput);
    }

    /**
     * Start the pipeline
     */
    protected function startPipeline(Pipeline $pipeline): void
    {
        $pipeline->update([
            'status' => Pipeline::STATUS_RUNNING,
            'started_at' => now(),
            'current_step' => Pipeline::STEPS[0],
            'current_step_progress' => 0,
        ]);

        PipelineLog::info($pipeline, 'orchestrator', 'Pipeline started in auto mode');
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

        PipelineLog::result($pipeline, 'orchestrator', 'Pipeline completed successfully', [
            'steps_completed' => count($results),
        ]);

        broadcast(new PipelineProgressEvent(
            $pipeline,
            'orchestrator',
            100,
            'completed',
            'Pipeline completed successfully!'
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

        PipelineLog::error($pipeline, 'orchestrator', 'Pipeline failed: ' . $error);

        broadcast(new PipelineProgressEvent(
            $pipeline,
            'orchestrator',
            0,
            'failed',
            $error
        ))->toOthers();
    }

    /**
     * Pause the pipeline
     */
    public function pausePipeline(Pipeline $pipeline): void
    {
        $pipeline->update([
            'status' => Pipeline::STATUS_PAUSED,
        ]);

        PipelineLog::info($pipeline, 'orchestrator', 'Pipeline paused');

        broadcast(new PipelineProgressEvent(
            $pipeline,
            $pipeline->current_step ?? 'orchestrator',
            $pipeline->current_step_progress,
            'paused',
            'Pipeline paused'
        ))->toOthers();
    }

    /**
     * Resume the pipeline
     */
    public function resumePipeline(Pipeline $pipeline): void
    {
        if ($pipeline->status !== Pipeline::STATUS_PAUSED) {
            throw new \RuntimeException('Pipeline is not paused');
        }

        $pipeline->update([
            'status' => Pipeline::STATUS_RUNNING,
        ]);

        PipelineLog::info($pipeline, 'orchestrator', 'Pipeline resumed');

        // Continue from current step
        $currentStep = $pipeline->current_step;
        if ($currentStep) {
            $this->runStep($pipeline, $currentStep);
        }
    }

    /**
     * Cancel the pipeline
     */
    public function cancelPipeline(Pipeline $pipeline): void
    {
        $pipeline->update([
            'status' => Pipeline::STATUS_FAILED,
            'error_message' => 'Pipeline cancelled by user',
        ]);

        PipelineLog::info($pipeline, 'orchestrator', 'Pipeline cancelled by user');

        broadcast(new PipelineProgressEvent(
            $pipeline,
            $pipeline->current_step ?? 'orchestrator',
            0,
            'cancelled',
            'Pipeline cancelled'
        ))->toOthers();
    }
}
