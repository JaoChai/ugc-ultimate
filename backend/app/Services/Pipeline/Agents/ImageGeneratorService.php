<?php

namespace App\Services\Pipeline\Agents;

use App\Models\AgentConfig;
use App\Models\ApiKey;
use App\Services\NanoBananaService;

class ImageGeneratorService extends BaseAgentService
{
    protected NanoBananaService $nanoBanana;

    public function __construct(
        \App\Services\OpenRouterService $openRouter,
        NanoBananaService $nanoBanana
    ) {
        parent::__construct($openRouter);
        $this->nanoBanana = $nanoBanana;
    }

    public function getAgentType(): string
    {
        return AgentConfig::TYPE_IMAGE_GENERATOR;
    }

    /**
     * Set Kie API key for Nano Banana
     */
    public function setKieApiKey(ApiKey $apiKey): self
    {
        $this->nanoBanana->setApiKey($apiKey);
        return $this;
    }

    /**
     * Execute image generation
     *
     * @param array $input [
     *   'scenes' => array,
     *   'style_guide' => array
     * ]
     */
    public function execute(array $input): array
    {
        $scenes = $input['scenes'] ?? [];
        $styleGuide = $input['style_guide'] ?? [];

        if (empty($scenes)) {
            throw new \RuntimeException('No scenes provided for image generation');
        }

        $this->logInfo("Starting image generation for " . count($scenes) . " scenes");
        $this->logProgress(5, "Preparing image generation...");

        $generatedImages = [];
        $taskIds = [];
        $totalScenes = count($scenes);

        // Step 1: Submit all image generation tasks
        $this->logThinking("Submitting {$totalScenes} image generation tasks to Nano Banana...");

        foreach ($scenes as $index => $scene) {
            $sceneNumber = $scene['number'] ?? ($index + 1);
            $prompt = $this->buildImagePrompt($scene, $styleGuide);

            try {
                $this->logInfo("Generating image for scene {$sceneNumber}...");

                $result = $this->nanoBanana->generate(
                    prompt: $prompt,
                    aspectRatio: $styleGuide['aspect_ratio'] ?? '16:9',
                    resolution: NanoBananaService::RESOLUTION_2K,
                    model: NanoBananaService::MODEL_PRO
                );

                $taskId = $result['taskId'] ?? $result['task_id'] ?? null;

                if ($taskId) {
                    $taskIds[$sceneNumber] = $taskId;
                }

                $generatedImages[$sceneNumber] = [
                    'scene_number' => $sceneNumber,
                    'prompt' => $prompt,
                    'task_id' => $taskId,
                    'status' => 'pending',
                ];

            } catch (\Exception $e) {
                $this->logError("Failed to submit scene {$sceneNumber}: " . $e->getMessage());
                $generatedImages[$sceneNumber] = [
                    'scene_number' => $sceneNumber,
                    'prompt' => $prompt,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }

            // Update progress
            $submitProgress = (int)(($index + 1) / $totalScenes * 40) + 5;
            $this->logProgress($submitProgress, "Submitted {$index + 1}/{$totalScenes} images...");
        }

        // Step 2: Wait for all tasks to complete
        $this->logProgress(50, "Waiting for image generation to complete...");
        $this->logThinking("Polling Nano Banana for task completion...");

        $completedCount = 0;
        $maxAttempts = 120; // 10 minutes max
        $attempt = 0;

        while ($completedCount < count($taskIds) && $attempt < $maxAttempts) {
            foreach ($taskIds as $sceneNumber => $taskId) {
                if ($generatedImages[$sceneNumber]['status'] === 'completed') {
                    continue;
                }

                try {
                    $status = $this->nanoBanana->getTaskStatus($taskId);
                    $state = $status['status'] ?? $status['state'] ?? 'pending';

                    if ($state === 'completed' || $state === 'success') {
                        $imageUrl = $status['data']['output']
                            ?? $status['output']
                            ?? $status['result']['output']
                            ?? $status['image_url']
                            ?? null;

                        $generatedImages[$sceneNumber]['status'] = 'completed';
                        $generatedImages[$sceneNumber]['image_url'] = $imageUrl;
                        $completedCount++;

                        $this->logInfo("Scene {$sceneNumber} image completed");
                    } elseif ($state === 'failed' || $state === 'error') {
                        $generatedImages[$sceneNumber]['status'] = 'failed';
                        $generatedImages[$sceneNumber]['error'] = $status['error'] ?? 'Unknown error';
                        $completedCount++;

                        $this->logError("Scene {$sceneNumber} image failed: " . ($status['error'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e) {
                    // Log but continue
                    $this->logError("Error checking scene {$sceneNumber}: " . $e->getMessage());
                }
            }

            // Update progress
            $completionProgress = (int)($completedCount / count($taskIds) * 45) + 50;
            $this->logProgress($completionProgress, "Completed {$completedCount}/{$totalScenes} images...");

            if ($completedCount < count($taskIds)) {
                sleep(5);
                $attempt++;
            }
        }

        // Step 3: Quality check with LLM (optional)
        $this->logProgress(95, "Finalizing image results...");

        // Prepare final result
        $images = [];
        $failedCount = 0;

        foreach ($generatedImages as $sceneNumber => $image) {
            if ($image['status'] === 'completed' && isset($image['image_url'])) {
                $images[] = [
                    'scene_number' => $sceneNumber,
                    'image_url' => $image['image_url'],
                    'prompt' => $image['prompt'],
                ];
            } else {
                $failedCount++;
            }
        }

        $this->logResult("Image generation completed", [
            'total' => $totalScenes,
            'completed' => count($images),
            'failed' => $failedCount,
        ]);
        $this->logProgress(100, "All images ready");

        return [
            'images' => $images,
            'total_generated' => count($images),
            'total_failed' => $failedCount,
            'details' => $generatedImages,
        ];
    }

    protected function buildImagePrompt(array $scene, array $styleGuide): string
    {
        $basePrompt = $scene['image_prompt'] ?? $scene['description'] ?? '';
        $artStyle = $styleGuide['art_style'] ?? '';
        $colorPalette = implode(', ', $styleGuide['color_palette'] ?? []);

        // Add style modifiers for better generation
        $prompt = $basePrompt;

        if ($artStyle) {
            $prompt .= ". Style: {$artStyle}";
        }

        if ($colorPalette) {
            $prompt .= ". Color scheme: {$colorPalette}";
        }

        // Add quality modifiers
        $prompt .= ". High quality, detailed, 16:9 aspect ratio, professional photography";

        return $prompt;
    }
}
