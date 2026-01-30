<?php

namespace App\Services\Pipeline\Agents;

use App\Models\AgentConfig;

class ThemeDirectorService extends BaseAgentService
{
    public function getAgentType(): string
    {
        return AgentConfig::TYPE_THEME_DIRECTOR;
    }

    /**
     * Execute theme direction
     *
     * @param array $input [
     *   'theme' => string,
     *   'duration' => int (seconds),
     *   'platform' => string (youtube, tiktok, etc.)
     * ]
     */
    public function execute(array $input): array
    {
        $theme = $input['theme'] ?? '';
        $duration = $input['duration'] ?? 60;
        $platform = $input['platform'] ?? 'youtube';

        $this->logInfo("Starting theme analysis for: {$theme}");
        $this->logProgress(10, "Analyzing theme...");

        // Build user prompt
        $userPrompt = $this->buildPrompt($theme, $duration, $platform);

        $this->logProgress(30, "Generating concept...");
        $this->logThinking("Analyzing theme and creating creative concept...");

        // Call LLM
        $result = $this->callLlm($userPrompt);

        $this->logProgress(90, "Finalizing concept...");

        // Validate and enhance result
        $concept = $this->processResult($result, $theme, $duration);

        $this->logResult("Theme concept created", $concept);
        $this->logProgress(100, "Theme direction completed");

        return $concept;
    }

    protected function buildPrompt(string $theme, int $duration, string $platform): string
    {
        return <<<PROMPT
Create a comprehensive concept for a music video based on the following:

Theme: {$theme}
Duration: {$duration} seconds
Platform: {$platform}

Please analyze the theme and generate a creative concept that would work well for this platform and duration.
PROMPT;
    }

    protected function processResult(array $result, string $theme, int $duration): array
    {
        // Ensure all required fields are present
        return [
            'title' => $result['title'] ?? $theme,
            'description' => $result['description'] ?? '',
            'mood' => $result['mood'] ?? 'neutral',
            'style' => $result['style'] ?? 'cinematic',
            'target_audience' => $result['target_audience'] ?? 'general',
            'keywords' => $result['keywords'] ?? [],
            'color_palette' => $result['color_palette'] ?? ['#000000', '#FFFFFF'],
            'duration' => $duration,
            'original_theme' => $theme,
        ];
    }
}
