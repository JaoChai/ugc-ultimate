<?php

namespace App\Services\Pipeline\Agents;

use App\Models\AgentConfig;
use App\Models\ApiKey;
use App\Services\SunoService;

class SunoExpertService extends BaseAgentService
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
        return AgentConfig::TYPE_SUNO_EXPERT;
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
     * Execute Suno optimization and music generation
     *
     * @param array $input [
     *   'song_concept' => array (from SongArchitect)
     * ]
     */
    public function execute(array $input): array
    {
        $songConcept = $input['song_concept'] ?? [];

        $this->logInfo("Starting Suno optimization");
        $this->logProgress(10, "Analyzing song concept for Suno...");

        // Step 1: Optimize for Suno best practices
        $this->logThinking("Applying Suno best practices...");

        $userPrompt = $this->buildOptimizationPrompt($songConcept);

        $this->logProgress(20, "Optimizing lyrics and style...");

        $optimizedResult = $this->callLlm($userPrompt);

        $this->logProgress(40, "Suno optimization complete");

        // Step 2: Generate music with Suno
        $this->logInfo("Sending to Suno API...");
        $this->logProgress(50, "Generating music with Suno V5...");

        $sunoResult = $this->generateWithSuno($optimizedResult);

        $this->logProgress(60, "Suno task created, waiting for completion...");

        // Step 3: Wait for Suno completion
        $taskId = $sunoResult['taskId'] ?? $sunoResult['task_id'] ?? null;

        if ($taskId) {
            $this->logThinking("Waiting for Suno to generate music (this may take 1-3 minutes)...");
            $completedResult = $this->waitForSuno($taskId);
            $this->logProgress(90, "Music generation completed");
        } else {
            $completedResult = $sunoResult;
            $this->logError("No task ID returned from Suno API");
        }

        // Step 4: Process and return result
        $result = $this->processResult($optimizedResult, $completedResult);

        $this->logResult("Suno music generation completed", [
            'task_id' => $taskId,
            'versions_count' => count($result['versions'] ?? []),
        ]);

        $this->logProgress(100, "Music ready for selection");

        return $result;
    }

    protected function buildOptimizationPrompt(array $songConcept): string
    {
        $conceptJson = json_encode($songConcept, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Optimize this song concept for Suno AI music generation:

{$conceptJson}

Apply all Suno best practices:
1. Add proper section tags [Intro], [Verse], [Chorus], etc.
2. Keep style under 200 characters
3. Keep title under 80 characters
4. Ensure lyrics are under 3000 characters
5. Use English for style tags
6. Be specific with genre, mood, and instruments
PROMPT;
    }

    protected function generateWithSuno(array $optimizedResult): array
    {
        $lyrics = $optimizedResult['optimized_lyrics'] ?? '';
        $style = $optimizedResult['suno_style'] ?? 'pop, catchy, melodic';
        $title = $optimizedResult['suno_title'] ?? 'Generated Song';
        $instrumental = $optimizedResult['instrumental'] ?? false;

        // Use custom mode when we have lyrics
        $hasLyrics = !empty($lyrics) && !$instrumental;

        return $this->sunoService->generate(
            prompt: $hasLyrics ? $lyrics : ($optimizedResult['suno_prompt'] ?? $title),
            model: SunoService::MODEL_V5,
            customMode: $hasLyrics,
            instrumental: $instrumental,
            style: $style,
            title: $title
        );
    }

    protected function waitForSuno(string $taskId): array
    {
        $maxAttempts = 60; // 5 minutes max (5 sec intervals)
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $status = $this->sunoService->getTaskStatus($taskId);

            $state = $status['status'] ?? $status['state'] ?? 'pending';

            if ($state === 'completed' || $state === 'success' || $state === 'done') {
                return $status;
            }

            if ($state === 'failed' || $state === 'error') {
                throw new \RuntimeException('Suno generation failed: ' . ($status['error'] ?? 'Unknown error'));
            }

            // Log progress periodically
            if ($attempt % 6 === 0) { // Every 30 seconds
                $this->logInfo("Still generating music... ({$attempt} attempts)");
            }

            sleep(5);
            $attempt++;
        }

        throw new \RuntimeException('Suno generation timeout after 5 minutes');
    }

    protected function processResult(array $optimizedResult, array $sunoResult): array
    {
        // Extract all versions from Suno result (usually 2)
        $sunoData = $sunoResult['response']['sunoData']
            ?? $sunoResult['data']['sunoData']
            ?? $sunoResult['sunoData']
            ?? [];

        $versions = [];
        foreach ($sunoData as $index => $data) {
            $versions[] = [
                'index' => $index,
                'audio_url' => $data['audioUrl'] ?? $data['audio_url'] ?? null,
                'clip_id' => $data['clipId'] ?? $data['id'] ?? null,
                'duration' => $data['duration'] ?? null,
                'title' => $data['title'] ?? null,
            ];
        }

        return [
            'optimized_lyrics' => $optimizedResult['optimized_lyrics'] ?? '',
            'suno_style' => $optimizedResult['suno_style'] ?? '',
            'suno_title' => $optimizedResult['suno_title'] ?? '',
            'recommendations_applied' => $optimizedResult['recommendations_applied'] ?? [],
            'quality_checks' => $optimizedResult['quality_checks'] ?? [],
            'versions' => $versions,
            'task_id' => $sunoResult['taskId'] ?? $sunoResult['task_id'] ?? null,
            'raw_result' => $sunoResult,
        ];
    }
}
