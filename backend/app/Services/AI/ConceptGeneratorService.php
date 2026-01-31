<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use App\Services\OpenRouterService;

class ConceptGeneratorService
{
    protected OpenRouterService $openRouter;

    // Default model for concept generation
    public const DEFAULT_MODEL = 'google/gemini-2.0-flash-exp';

    public function __construct(OpenRouterService $openRouter)
    {
        $this->openRouter = $openRouter;
    }

    public function setApiKey(ApiKey $apiKey): self
    {
        $this->openRouter->setApiKey($apiKey);
        return $this;
    }

    /**
     * Map thinking level to temperature
     * Higher thinking = more exploration = higher temperature
     */
    protected function mapThinkingToTemperature(string $thinkingLevel): float
    {
        return match ($thinkingLevel) {
            'none' => 0.5,
            'low' => 0.6,
            'medium' => 0.7,
            'high' => 0.8,
            default => 0.7,
        };
    }

    /**
     * Generate complete project concept from a theme
     */
    public function generateFullConcept(string $theme, array $options = []): array
    {
        $thinkingLevel = $options['thinking_level'] ?? 'medium';

        // Step 1: Generate music concept
        $musicConcept = $this->generateMusicConcept($theme, $options, $thinkingLevel);

        // Step 2: Generate lyrics based on music concept
        $lyrics = $this->generateLyrics($musicConcept, $options, $thinkingLevel);

        // Step 3: Generate visual concept
        $visualConcept = $this->generateVisualConcept($musicConcept, $lyrics, $options, $thinkingLevel);

        return [
            'theme' => $theme,
            'music' => $musicConcept,
            'lyrics' => $lyrics,
            'visual' => $visualConcept,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate music concept only
     */
    public function generateMusicConcept(string $theme, array $options = [], string $thinkingLevel = 'medium'): array
    {
        $prompt = PromptTemplates::musicConcept($theme, $options);
        $temperature = $this->mapThinkingToTemperature($thinkingLevel);

        try {
            return $this->openRouter->generateJson(
                PromptTemplates::musicConceptSystem(),
                $prompt,
                PromptTemplates::musicConceptSchema(),
                self::DEFAULT_MODEL,
                $temperature
            );
        } catch (\Exception $e) {
            // Fallback: try with regular generation and parse manually
            $response = $this->openRouter->chat(
                PromptTemplates::musicConceptSystem(),
                $prompt,
                self::DEFAULT_MODEL,
                $temperature
            );

            $content = $this->openRouter->extractContent($response);
            return $this->parseJsonFromText($content);
        }
    }

    /**
     * Generate lyrics based on concept
     */
    public function generateLyrics(array $musicConcept, array $options = [], string $thinkingLevel = 'medium'): string
    {
        $prompt = PromptTemplates::lyrics($musicConcept, $options);
        // Use higher temperature for lyrics (creativity)
        $temperature = max($this->mapThinkingToTemperature($thinkingLevel), 0.8);

        $response = $this->openRouter->chat(
            PromptTemplates::lyricsSystem(),
            $prompt,
            self::DEFAULT_MODEL,
            $temperature
        );

        return $this->openRouter->extractContent($response);
    }

    /**
     * Generate visual concept
     */
    public function generateVisualConcept(array $musicConcept, string $lyrics, array $options = [], string $thinkingLevel = 'medium'): array
    {
        $prompt = PromptTemplates::visualConcept($musicConcept, $lyrics, $options);
        $temperature = $this->mapThinkingToTemperature($thinkingLevel);

        try {
            return $this->openRouter->generateJson(
                PromptTemplates::visualConceptSystem(),
                $prompt,
                PromptTemplates::visualConceptSchema(),
                self::DEFAULT_MODEL,
                $temperature
            );
        } catch (\Exception $e) {
            // Fallback
            $response = $this->openRouter->chat(
                PromptTemplates::visualConceptSystem(),
                $prompt,
                self::DEFAULT_MODEL,
                $temperature
            );

            $content = $this->openRouter->extractContent($response);
            return $this->parseJsonFromText($content);
        }
    }

    /**
     * Generate image prompts for each scene
     */
    public function generateImagePrompts(array $visualConcept): array
    {
        $prompts = [];
        $style = $visualConcept['overallStyle'] ?? 'cinematic';

        foreach ($visualConcept['scenes'] ?? [] as $scene) {
            $prompts[] = [
                'scene_number' => $scene['sceneNumber'] ?? count($prompts) + 1,
                'section' => $scene['section'] ?? 'unknown',
                'prompt' => $scene['imagePrompt'] ?? $this->buildImagePrompt($scene, $style),
                'duration' => $scene['duration'] ?? '5s',
            ];
        }

        return $prompts;
    }

    /**
     * Generate video prompts for each scene
     */
    public function generateVideoPrompts(array $visualConcept): array
    {
        $prompts = [];

        foreach ($visualConcept['scenes'] ?? [] as $scene) {
            $prompts[] = [
                'scene_number' => $scene['sceneNumber'] ?? count($prompts) + 1,
                'section' => $scene['section'] ?? 'unknown',
                'prompt' => $scene['videoPrompt'] ?? $this->buildVideoPrompt($scene),
                'duration' => $scene['duration'] ?? '5s',
                'camera_movement' => $scene['cameraMovement'] ?? 'static',
                'transition' => $scene['transition'] ?? 'cut',
            ];
        }

        return $prompts;
    }

    /**
     * Build Suno music prompt from concept
     */
    public function buildSunoPrompt(array $musicConcept): string
    {
        $parts = [];

        // Genre and style
        $parts[] = $musicConcept['genre'] ?? 'pop';
        if (!empty($musicConcept['subGenre'])) {
            $parts[] = $musicConcept['subGenre'];
        }

        // Mood and energy
        $parts[] = $musicConcept['mood'] ?? 'upbeat';
        $parts[] = ($musicConcept['energy'] ?? 'medium') . ' energy';

        // Instruments
        if (!empty($musicConcept['instruments'])) {
            $parts[] = 'featuring ' . implode(', ', array_slice($musicConcept['instruments'], 0, 3));
        }

        // Tempo
        if (!empty($musicConcept['tempo'])) {
            $parts[] = $musicConcept['tempo'] . ' BPM';
        }

        // Additional keywords
        if (!empty($musicConcept['keywords'])) {
            $parts = array_merge($parts, array_slice($musicConcept['keywords'], 0, 3));
        }

        return implode(', ', $parts);
    }

    /**
     * Parse JSON from text that might contain markdown
     */
    protected function parseJsonFromText(string $text): array
    {
        // Try direct parse
        $decoded = json_decode($text, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Try to find JSON object in text
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        throw new \RuntimeException('Failed to parse JSON from response');
    }

    /**
     * Build image prompt from scene description
     */
    protected function buildImagePrompt(array $scene, string $style): string
    {
        $parts = [];

        $parts[] = $scene['description'] ?? 'A cinematic scene';
        $parts[] = $style;

        if (!empty($scene['cameraMovement'])) {
            $parts[] = $scene['cameraMovement'] . ' shot';
        }

        $parts[] = 'high quality';
        $parts[] = '8k';
        $parts[] = 'detailed';

        return implode(', ', $parts);
    }

    /**
     * Build video prompt from scene
     */
    protected function buildVideoPrompt(array $scene): string
    {
        $parts = [];

        $parts[] = $scene['description'] ?? 'A cinematic scene';

        if (!empty($scene['cameraMovement'])) {
            $parts[] = 'camera: ' . $scene['cameraMovement'];
        }

        $parts[] = 'smooth motion';
        $parts[] = 'cinematic';

        return implode(', ', $parts);
    }
}
