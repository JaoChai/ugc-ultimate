<?php

namespace App\Services\Pipeline\Agents;

use App\Models\AgentConfig;
use App\Models\ApiKey;
use App\Services\NanoBananaService;

class VisualDesignerService extends BaseAgentService
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
        return AgentConfig::TYPE_VISUAL_DESIGNER;
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
     * Execute visual design and image generation
     *
     * @param array $input [
     *   'hook' => string,
     *   'song_title' => string,
     *   'mood' => string,
     *   'genre' => string,
     *   'selected_audio_url' => string
     * ]
     */
    public function execute(array $input): array
    {
        $hook = $input['hook'] ?? '';
        $songTitle = $input['song_title'] ?? 'Untitled';
        $mood = $input['mood'] ?? 'neutral';
        $genre = $input['genre'] ?? 'pop';

        $this->logInfo("Starting visual design for: {$songTitle}");
        $this->logProgress(10, "Analyzing song concept for visual...");

        // Step 1: Generate visual concept using LLM
        $this->logThinking("Creating visual concept based on hook and title...");

        $userPrompt = $this->buildVisualPrompt($hook, $songTitle, $mood, $genre);

        $this->logProgress(20, "Designing visual concept...");

        $visualConcept = $this->callLlm($userPrompt);

        $this->logProgress(40, "Visual concept ready");

        // Step 2: Generate image with Nano Banana
        $imagePrompt = $visualConcept['image_prompt'] ?? '';

        if (empty($imagePrompt)) {
            $this->logError("No image prompt generated");
            throw new \RuntimeException('Failed to generate image prompt');
        }

        $this->logInfo("Sending to Nano Banana API...");
        $this->logProgress(50, "Generating image...");

        $imageResult = $this->generateImage($imagePrompt);

        $this->logProgress(80, "Image generation complete");

        // Step 3: Wait for image completion if needed
        $taskId = $imageResult['taskId'] ?? $imageResult['task_id'] ?? null;
        $imageUrl = null;

        if ($taskId) {
            $this->logThinking("Waiting for image generation...");
            $completedResult = $this->waitForImage($taskId);
            $imageUrl = $this->extractImageUrl($completedResult);
        } else {
            $imageUrl = $this->extractImageUrl($imageResult);
        }

        if (!$imageUrl) {
            $this->logError("Failed to get image URL");
            throw new \RuntimeException('Failed to generate image');
        }

        $this->logProgress(95, "Image ready");

        $result = [
            'visual_concept' => $visualConcept['visual_concept'] ?? '',
            'image_prompt' => $imagePrompt,
            'image_url' => $imageUrl,
            'aspect_ratio' => '16:9',
            'style_references' => $visualConcept['style_references'] ?? [],
            'color_palette' => $visualConcept['color_palette'] ?? [],
            'composition' => $visualConcept['composition'] ?? [],
            'mood_alignment' => $visualConcept['mood_alignment'] ?? [],
            'task_id' => $taskId,
        ];

        $this->logResult("Visual design completed", [
            'image_url' => $imageUrl,
            'concept' => $visualConcept['visual_concept'] ?? '',
        ]);

        $this->logProgress(100, "Ready for video composition");

        return $result;
    }

    protected function buildVisualPrompt(string $hook, string $songTitle, string $mood, string $genre): string
    {
        return <<<PROMPT
Create a compelling visual concept for a music video based on:

## Song Information
- **Title**: {$songTitle}
- **Hook**: {$hook}
- **Mood**: {$mood}
- **Genre**: {$genre}

## Your Task
1. Design a single powerful image that captures the essence of the hook
2. The image will be used as a static background for the music video
3. Aspect ratio must be 16:9 (widescreen)
4. Create a detailed prompt for AI image generation (100-300 words)
5. NO text or words in the image
6. Consider the emotional connection between visual and song

Make the visual striking and memorable, suitable for a music video thumbnail.
PROMPT;
    }

    protected function generateImage(string $prompt): array
    {
        // Add required specifications to prompt
        $fullPrompt = $prompt . ', 16:9 aspect ratio, high quality, no text, no watermarks, no logos';

        return $this->nanoBanana->generate(
            prompt: $fullPrompt,
            aspectRatio: '16:9',
            resolution: NanoBananaService::RESOLUTION_2K,
            model: NanoBananaService::MODEL_PRO
        );
    }

    protected function waitForImage(string $taskId): array
    {
        $maxAttempts = 60; // 5 minutes max
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $status = $this->nanoBanana->getTaskStatus($taskId);

            $state = $status['status'] ?? $status['state'] ?? 'pending';

            if ($state === 'completed' || $state === 'success' || $state === 'done') {
                return $status;
            }

            if ($state === 'failed' || $state === 'error') {
                throw new \RuntimeException('Image generation failed: ' . ($status['error'] ?? 'Unknown error'));
            }

            if ($attempt % 6 === 0) {
                $this->logInfo("Still generating image... ({$attempt} attempts)");
            }

            sleep(5);
            $attempt++;
        }

        throw new \RuntimeException('Image generation timeout');
    }

    protected function extractImageUrl(array $result): ?string
    {
        // Try various response formats
        return $result['response']['imageUrl']
            ?? $result['data']['imageUrl']
            ?? $result['imageUrl']
            ?? $result['image_url']
            ?? $result['url']
            ?? null;
    }
}
