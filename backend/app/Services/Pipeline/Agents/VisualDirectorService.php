<?php

namespace App\Services\Pipeline\Agents;

use App\Models\AgentConfig;

class VisualDirectorService extends BaseAgentService
{
    public function getAgentType(): string
    {
        return AgentConfig::TYPE_VISUAL_DIRECTOR;
    }

    /**
     * Execute visual direction
     *
     * @param array $input [
     *   'theme_concept' => array,
     *   'music_concept' => array,
     *   'duration' => int
     * ]
     */
    public function execute(array $input): array
    {
        $themeConcept = $input['theme_concept'] ?? [];
        $musicConcept = $input['music_concept'] ?? [];
        $duration = $input['duration'] ?? 60;

        $this->logInfo("Starting visual direction for: " . ($themeConcept['title'] ?? 'Untitled'));
        $this->logProgress(10, "Analyzing theme and music...");

        // Step 1: Analyze inputs
        $this->logThinking("Analyzing theme concept and music structure...");
        $this->logProgress(20, "Creating storyboard...");

        // Step 2: Generate visual concept
        $userPrompt = $this->buildPrompt($themeConcept, $musicConcept, $duration);
        $result = $this->callLlm($userPrompt);

        $this->logProgress(70, "Refining scene descriptions...");

        // Step 3: Process and validate scenes
        $scenes = $this->processScenes($result, $themeConcept, $duration);

        $this->logProgress(90, "Generating image prompts...");

        // Step 4: Create final output
        $output = [
            'scenes' => $scenes,
            'style_guide' => $this->createStyleGuide($themeConcept, $result),
            'total_duration' => $duration,
            'scene_count' => count($scenes),
        ];

        $this->logResult("Visual direction completed", [
            'scene_count' => count($scenes),
            'total_duration' => $duration,
        ]);
        $this->logProgress(100, "Storyboard ready");

        return $output;
    }

    protected function buildPrompt(array $themeConcept, array $musicConcept, int $duration): string
    {
        $title = $themeConcept['title'] ?? 'Untitled';
        $mood = $themeConcept['mood'] ?? 'neutral';
        $style = $themeConcept['style'] ?? 'cinematic';
        $colorPalette = implode(', ', $themeConcept['color_palette'] ?? []);
        $lyrics = $musicConcept['lyrics'] ?? '';
        $lyricsSegments = json_encode($musicConcept['lyrics_segments'] ?? [], JSON_PRETTY_PRINT);

        return <<<PROMPT
Create a visual storyboard for a music video based on:

Title: {$title}
Mood: {$mood}
Visual Style: {$style}
Color Palette: {$colorPalette}
Total Duration: {$duration} seconds

Lyrics:
{$lyrics}

Lyrics Segments (for timing reference):
{$lyricsSegments}

Create scenes that:
1. Match the emotional arc of the lyrics
2. Maintain visual consistency with the style guide
3. Include detailed image prompts for AI image generation
4. Specify appropriate transitions between scenes
PROMPT;
    }

    protected function processScenes(array $result, array $themeConcept, int $duration): array
    {
        $rawScenes = $result['scenes'] ?? [];
        $style = $themeConcept['style'] ?? 'cinematic';
        $mood = $themeConcept['mood'] ?? 'neutral';

        // Calculate scene duration if not specified
        $sceneCount = count($rawScenes);
        $defaultDuration = $sceneCount > 0 ? $duration / $sceneCount : 5;

        $processedScenes = [];
        $currentTime = 0;

        foreach ($rawScenes as $index => $scene) {
            $sceneDuration = $scene['duration'] ?? $defaultDuration;

            $processedScenes[] = [
                'number' => $index + 1,
                'section' => $scene['section'] ?? 'scene_' . ($index + 1),
                'start_time' => $currentTime,
                'end_time' => $currentTime + $sceneDuration,
                'duration' => $sceneDuration,
                'description' => $scene['description'] ?? '',
                'image_prompt' => $this->enhanceImagePrompt(
                    $scene['image_prompt'] ?? $scene['description'] ?? '',
                    $style,
                    $mood,
                    $themeConcept['color_palette'] ?? []
                ),
                'transition' => $scene['transition'] ?? 'fade',
            ];

            $currentTime += $sceneDuration;
        }

        return $processedScenes;
    }

    protected function enhanceImagePrompt(string $prompt, string $style, string $mood, array $colorPalette): string
    {
        // Add style and quality modifiers
        $styleModifiers = match ($style) {
            'anime' => 'anime style, vibrant colors, dynamic composition',
            'realistic' => 'photorealistic, high detail, natural lighting',
            'abstract' => 'abstract art, bold shapes, artistic interpretation',
            'cinematic' => 'cinematic lighting, dramatic composition, film quality',
            'minimalist' => 'minimalist design, clean lines, simple composition',
            default => 'high quality, professional',
        };

        $moodModifiers = match ($mood) {
            'happy' => 'bright, cheerful, warm tones',
            'sad' => 'melancholic, muted colors, soft lighting',
            'energetic' => 'dynamic, vibrant, high contrast',
            'calm' => 'serene, soft focus, pastel tones',
            'romantic' => 'warm, soft lighting, dreamy atmosphere',
            'dark' => 'moody, shadows, dramatic contrast',
            default => '',
        };

        $colorNote = !empty($colorPalette)
            ? 'Color palette: ' . implode(', ', $colorPalette)
            : '';

        return trim("{$prompt}. {$styleModifiers}. {$moodModifiers}. {$colorNote}");
    }

    protected function createStyleGuide(array $themeConcept, array $result): array
    {
        return [
            'art_style' => $result['style_guide']['art_style'] ?? $themeConcept['style'] ?? 'cinematic',
            'color_palette' => $result['style_guide']['color_palette'] ?? $themeConcept['color_palette'] ?? [],
            'character_consistency' => $result['style_guide']['character_consistency'] ?? 'Maintain consistent character appearance across all scenes',
            'lighting' => $result['style_guide']['lighting'] ?? 'Consistent lighting matching the mood',
            'aspect_ratio' => '16:9',
        ];
    }
}
