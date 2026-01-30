<?php

namespace App\Services\Pipeline\Agents;

use App\Models\AgentConfig;

class VideoComposerService extends BaseAgentService
{
    public function getAgentType(): string
    {
        return AgentConfig::TYPE_VIDEO_COMPOSER;
    }

    /**
     * Execute video composition
     *
     * @param array $input [
     *   'images' => array,
     *   'music_url' => string,
     *   'scenes' => array,
     *   'duration' => int
     * ]
     */
    public function execute(array $input): array
    {
        $images = $input['images'] ?? [];
        $musicUrl = $input['music_url'] ?? null;
        $scenes = $input['scenes'] ?? [];
        $duration = $input['duration'] ?? 60;

        if (empty($images)) {
            throw new \RuntimeException('No images provided for video composition');
        }

        $this->logInfo("Starting video composition with " . count($images) . " images");
        $this->logProgress(10, "Preparing composition...");

        // Step 1: Generate composition instructions using LLM
        $this->logThinking("Creating composition timeline...");
        $compositionPlan = $this->generateCompositionPlan($images, $scenes, $duration);
        $this->logProgress(30, "Composition plan created");

        // Step 2: Build FFmpeg composition data
        $this->logProgress(50, "Building video composition...");
        $composition = $this->buildComposition($images, $compositionPlan, $scenes);

        // Step 3: Prepare output data for FFmpegService
        $output = [
            'images' => $this->prepareImagesForFfmpeg($images, $composition),
            'audio_url' => $musicUrl,
            'composition' => $composition,
            'settings' => [
                'resolution' => '1920x1080',
                'fps' => 30,
                'format' => 'mp4',
                'codec' => 'libx264',
                'audio_codec' => 'aac',
            ],
            'total_duration' => $duration,
        ];

        $this->logResult("Video composition prepared", [
            'image_count' => count($images),
            'total_duration' => $duration,
            'has_audio' => !empty($musicUrl),
        ]);
        $this->logProgress(100, "Composition ready for encoding");

        return $output;
    }

    protected function generateCompositionPlan(array $images, array $scenes, int $duration): array
    {
        $imageCount = count($images);
        $sceneInfo = json_encode(array_map(function ($scene) {
            return [
                'number' => $scene['number'] ?? 0,
                'section' => $scene['section'] ?? '',
                'duration' => $scene['duration'] ?? 0,
                'transition' => $scene['transition'] ?? 'fade',
            ];
        }, $scenes), JSON_PRETTY_PRINT);

        $userPrompt = <<<PROMPT
Create video composition instructions for:
- {$imageCount} images
- Total duration: {$duration} seconds

Scene information:
{$sceneInfo}

Provide timing and effects for each scene. Include Ken Burns effects (zoom, pan) for visual interest.
PROMPT;

        return $this->callLlm($userPrompt);
    }

    protected function buildComposition(array $images, array $compositionPlan, array $scenes): array
    {
        $composition = $compositionPlan['composition'] ?? [];

        // If LLM didn't provide composition, create default
        if (empty($composition)) {
            $composition = $this->createDefaultComposition($images, $scenes);
        }

        // Ensure each composition entry has required fields
        return array_map(function ($item, $index) {
            return [
                'scene' => $item['scene'] ?? ($index + 1),
                'duration' => $item['duration'] ?? 5,
                'transition_in' => $item['transition_in'] ?? 'fade',
                'transition_out' => $item['transition_out'] ?? 'fade',
                'transition_duration' => $item['transition_duration'] ?? 0.5,
                'ken_burns' => $this->normalizeKenBurns($item['ken_burns'] ?? null),
            ];
        }, $composition, array_keys($composition));
    }

    protected function createDefaultComposition(array $images, array $scenes): array
    {
        $composition = [];

        foreach ($images as $index => $image) {
            $sceneNumber = $image['scene_number'] ?? ($index + 1);
            $scene = $scenes[$index] ?? null;

            $composition[] = [
                'scene' => $sceneNumber,
                'duration' => $scene['duration'] ?? 5,
                'transition_in' => $index === 0 ? 'fade' : ($scene['transition'] ?? 'fade'),
                'transition_out' => 'fade',
                'transition_duration' => 0.5,
                'ken_burns' => [
                    'zoom' => 1.0 + (($index % 3) * 0.05), // 1.0, 1.05, 1.10
                    'direction' => ['up', 'down', 'left', 'right'][$index % 4],
                ],
            ];
        }

        return $composition;
    }

    protected function normalizeKenBurns(?array $kenBurns): array
    {
        if (!$kenBurns) {
            return [
                'zoom' => 1.05,
                'direction' => 'up',
            ];
        }

        return [
            'zoom' => min(max($kenBurns['zoom'] ?? 1.05, 1.0), 1.3),
            'direction' => in_array($kenBurns['direction'] ?? '', ['up', 'down', 'left', 'right'])
                ? $kenBurns['direction']
                : 'up',
        ];
    }

    protected function prepareImagesForFfmpeg(array $images, array $composition): array
    {
        $prepared = [];

        foreach ($images as $image) {
            $sceneNumber = $image['scene_number'] ?? 0;
            $compositionData = null;

            // Find matching composition data
            foreach ($composition as $comp) {
                if ($comp['scene'] === $sceneNumber) {
                    $compositionData = $comp;
                    break;
                }
            }

            $prepared[] = [
                'scene_number' => $sceneNumber,
                'url' => $image['image_url'] ?? $image['url'] ?? '',
                'duration' => $compositionData['duration'] ?? 5,
                'transition_in' => $compositionData['transition_in'] ?? 'fade',
                'transition_out' => $compositionData['transition_out'] ?? 'fade',
                'ken_burns' => $compositionData['ken_burns'] ?? ['zoom' => 1.05, 'direction' => 'up'],
            ];
        }

        // Sort by scene number
        usort($prepared, fn($a, $b) => $a['scene_number'] <=> $b['scene_number']);

        return $prepared;
    }
}
