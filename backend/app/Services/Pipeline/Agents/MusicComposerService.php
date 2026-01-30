<?php

namespace App\Services\Pipeline\Agents;

use App\Models\AgentConfig;
use App\Models\ApiKey;
use App\Services\SunoService;

class MusicComposerService extends BaseAgentService
{
    protected SunoService $sunoService;

    public function __construct(
        \App\Services\OpenRouterService $openRouter,
        SunoService $sunoService
    ) {
        parent::__construct($openRouter);
        $this->sunoService = $sunoService;
    }

    public function getAgentType(): string
    {
        return AgentConfig::TYPE_MUSIC_COMPOSER;
    }

    /**
     * Set Kie API key for Suno
     */
    public function setKieApiKey(ApiKey $apiKey): self
    {
        $this->sunoService->setApiKey($apiKey);
        return $this;
    }

    /**
     * Execute music composition
     *
     * @param array $input [
     *   'theme_concept' => array (from ThemeDirector),
     *   'duration' => int (seconds)
     * ]
     */
    public function execute(array $input): array
    {
        $themeConcept = $input['theme_concept'] ?? [];
        $duration = $input['duration'] ?? 60;

        $this->logInfo("Starting music composition for: " . ($themeConcept['title'] ?? 'Untitled'));
        $this->logProgress(10, "Analyzing theme concept...");

        // Step 1: Generate music concept using LLM
        $this->logThinking("Creating music concept based on theme...");
        $musicConcept = $this->generateMusicConcept($themeConcept, $duration);
        $this->logProgress(30, "Music concept created");

        // Step 2: Generate music using Suno
        $this->logInfo("Sending to Suno API...");
        $this->logProgress(40, "Generating music with Suno v5...");

        $sunoResult = $this->generateWithSuno($musicConcept);
        $this->logProgress(60, "Suno task created, waiting for completion...");

        // Step 3: Wait for Suno completion
        $taskId = $sunoResult['task_id'] ?? null;
        if ($taskId) {
            $this->logThinking("Waiting for Suno to generate music...");
            $completedResult = $this->waitForSuno($taskId);
            $this->logProgress(90, "Music generation completed");
        } else {
            $completedResult = $sunoResult;
        }

        // Step 4: Process result
        $result = $this->processResult($musicConcept, $completedResult);

        $this->logResult("Music composition completed", $result);
        $this->logProgress(100, "Music ready");

        return $result;
    }

    protected function generateMusicConcept(array $themeConcept, int $duration): array
    {
        $title = $themeConcept['title'] ?? 'Untitled';
        $mood = $themeConcept['mood'] ?? 'neutral';
        $style = $themeConcept['style'] ?? 'cinematic';
        $keywords = implode(', ', $themeConcept['keywords'] ?? []);

        $userPrompt = <<<PROMPT
Create a music concept for a {$duration}-second song based on this theme:

Title: {$title}
Mood: {$mood}
Visual Style: {$style}
Keywords: {$keywords}

Generate a complete music concept including Suno prompt, lyrics, and segment timing.
PROMPT;

        return $this->callLlm($userPrompt);
    }

    protected function generateWithSuno(array $musicConcept): array
    {
        $sunoPrompt = $musicConcept['suno_prompt'] ?? '';
        $lyrics = $musicConcept['lyrics'] ?? null;
        $title = $musicConcept['title'] ?? 'Generated Song';
        $genre = $musicConcept['genre'] ?? 'pop';

        return $this->sunoService->generate(
            prompt: $sunoPrompt,
            lyrics: $lyrics,
            title: $title,
            style: $genre,
            model: SunoService::MODEL_V5
        );
    }

    protected function waitForSuno(string $taskId): array
    {
        $maxAttempts = 60; // 5 minutes max
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $status = $this->sunoService->getTaskStatus($taskId);

            $state = $status['status'] ?? $status['state'] ?? 'pending';

            if ($state === 'completed' || $state === 'success') {
                return $status;
            }

            if ($state === 'failed' || $state === 'error') {
                throw new \RuntimeException('Suno generation failed: ' . ($status['error'] ?? 'Unknown error'));
            }

            // Log progress periodically
            if ($attempt % 6 === 0) { // Every 30 seconds
                $this->logInfo("Still generating music... (attempt {$attempt})");
            }

            sleep(5);
            $attempt++;
        }

        throw new \RuntimeException('Suno generation timeout');
    }

    protected function processResult(array $musicConcept, array $sunoResult): array
    {
        // Extract audio URL from Suno result
        $audioUrl = $sunoResult['data']['audio_url']
            ?? $sunoResult['audio_url']
            ?? $sunoResult['result']['audio_url']
            ?? null;

        return [
            'title' => $musicConcept['title'] ?? 'Generated Song',
            'genre' => $musicConcept['genre'] ?? 'pop',
            'bpm' => $musicConcept['bpm'] ?? 120,
            'lyrics' => $musicConcept['lyrics'] ?? '',
            'lyrics_segments' => $musicConcept['lyrics_segments'] ?? [],
            'suno_prompt' => $musicConcept['suno_prompt'] ?? '',
            'audio_url' => $audioUrl,
            'task_id' => $sunoResult['task_id'] ?? null,
            'raw_result' => $sunoResult,
        ];
    }
}
