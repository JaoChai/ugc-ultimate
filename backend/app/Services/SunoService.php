<?php

namespace App\Services;

use App\Models\ApiKey;

class SunoService
{
    protected KieApiService $kieApi;

    // Available Suno models
    public const MODEL_V3_5 = 'chirp-v3-5';      // Legacy
    public const MODEL_V4 = 'chirp-v4';          // Vocal quality
    public const MODEL_V4_5 = 'chirp-v4-5';      // Smart prompts
    public const MODEL_V4_5_PLUS = 'chirp-v4-5-plus';  // Richer sound
    public const MODEL_V5 = 'chirp-v5';          // Latest - best quality

    // Default model (v5)
    public const DEFAULT_MODEL = self::MODEL_V5;

    public function __construct(KieApiService $kieApi)
    {
        $this->kieApi = $kieApi;
    }

    public function setApiKey(ApiKey $apiKey): self
    {
        $this->kieApi->setApiKeyFromModel($apiKey);
        return $this;
    }

    /**
     * Generate music with custom lyrics
     *
     * @param string $prompt Description of the song/style (max 1000 chars for v5)
     * @param string|null $lyrics Custom lyrics (optional)
     * @param string|null $title Song title (optional)
     * @param string|null $style Music style/genre (optional)
     * @param bool $instrumental Generate instrumental only
     * @param string $model Suno model version (default: v5)
     */
    public function generate(
        string $prompt,
        ?string $lyrics = null,
        ?string $title = null,
        ?string $style = null,
        bool $instrumental = false,
        string $model = self::DEFAULT_MODEL
    ): array {
        $payload = [
            'prompt' => $prompt,
            'model' => $model,
            'make_instrumental' => $instrumental,
        ];

        if ($lyrics) {
            $payload['lyrics'] = $lyrics;
        }

        if ($title) {
            $payload['title'] = $title;
        }

        if ($style) {
            $payload['style'] = $style;
        }

        return $this->kieApi->post('/api/v1/suno/generate', $payload);
    }

    /**
     * Generate music with auto-generated lyrics based on prompt
     */
    public function generateAuto(
        string $prompt,
        string $model = self::DEFAULT_MODEL
    ): array {
        return $this->kieApi->post('/api/v1/suno/generate', [
            'prompt' => $prompt,
            'model' => $model,
            'auto_lyrics' => true,
        ]);
    }

    /**
     * Generate lyrics from a prompt (v5 feature)
     *
     * @param string $prompt Description of what the lyrics should be about
     */
    public function generateLyrics(string $prompt): array
    {
        return $this->kieApi->post('/api/v1/suno/lyrics', [
            'prompt' => $prompt,
        ]);
    }

    /**
     * Extend an existing song (v5 enhanced - no artifacts)
     *
     * @param string $audioUrl URL of the audio to extend
     * @param string $prompt Extension prompt
     * @param int $continueAt Timestamp to continue from (seconds)
     */
    public function extend(
        string $audioUrl,
        string $prompt,
        int $continueAt = 0,
        string $model = self::DEFAULT_MODEL
    ): array {
        return $this->kieApi->post('/api/v1/suno/extend', [
            'audio_url' => $audioUrl,
            'prompt' => $prompt,
            'continue_at' => $continueAt,
            'model' => $model,
        ]);
    }

    /**
     * Extract stems from audio (v5 feature)
     * Separates vocals from instrumentals
     *
     * @param string $audioUrl URL of the audio to process
     */
    public function extractStems(string $audioUrl): array
    {
        return $this->kieApi->post('/api/v1/suno/stems', [
            'audio_url' => $audioUrl,
        ]);
    }

    /**
     * Create a cover version in different style (v5 feature)
     *
     * @param string $audioUrl URL of the original audio
     * @param string $style Target style/genre
     */
    public function createCover(string $audioUrl, string $style): array
    {
        return $this->kieApi->post('/api/v1/suno/cover', [
            'audio_url' => $audioUrl,
            'style' => $style,
        ]);
    }

    /**
     * Convert audio to MIDI (v5 feature)
     *
     * @param string $audioUrl URL of the audio to convert
     */
    public function convertToMidi(string $audioUrl): array
    {
        return $this->kieApi->post('/api/v1/suno/midi', [
            'audio_url' => $audioUrl,
        ]);
    }

    /**
     * Get task status
     */
    public function getTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/suno/record-info', ['task_id' => $taskId]);
    }

    /**
     * Wait for task completion
     */
    public function waitForCompletion(string $taskId, int $maxWaitSeconds = 300): array
    {
        $maxAttempts = $maxWaitSeconds / 5;

        return $this->kieApi->pollTaskStatus(
            '/api/v1/suno/record-info',
            $taskId,
            (int) $maxAttempts,
            5
        );
    }

    /**
     * Generate music and wait for completion (synchronous)
     */
    public function generateAndWait(
        string $prompt,
        ?string $lyrics = null,
        ?string $title = null,
        ?string $style = null,
        bool $instrumental = false,
        string $model = self::DEFAULT_MODEL
    ): array {
        $result = $this->generate($prompt, $lyrics, $title, $style, $instrumental, $model);

        $taskId = $result['task_id'] ?? null;

        if (!$taskId) {
            throw new \RuntimeException('No task ID returned from Suno API');
        }

        return $this->waitForCompletion($taskId);
    }

    /**
     * Available Suno models
     */
    public static function getModels(): array
    {
        return [
            self::MODEL_V3_5 => [
                'name' => 'Suno v3.5',
                'description' => 'Legacy - Structured songs',
                'max_prompt_chars' => 500,
            ],
            self::MODEL_V4 => [
                'name' => 'Suno v4',
                'description' => 'Improved vocal quality',
                'max_prompt_chars' => 1000,
            ],
            self::MODEL_V4_5 => [
                'name' => 'Suno v4.5',
                'description' => 'Smart prompts',
                'max_prompt_chars' => 1000,
            ],
            self::MODEL_V4_5_PLUS => [
                'name' => 'Suno v4.5+',
                'description' => 'Richer sound',
                'max_prompt_chars' => 1000,
            ],
            self::MODEL_V5 => [
                'name' => 'Suno v5',
                'description' => 'Latest - Studio quality, best musicality',
                'max_prompt_chars' => 1000,
            ],
        ];
    }

    /**
     * Available music styles/genres
     */
    public static function getStyles(): array
    {
        return [
            'pop' => 'Pop',
            'rock' => 'Rock',
            'electronic' => 'Electronic',
            'hip-hop' => 'Hip Hop',
            'r&b' => 'R&B',
            'jazz' => 'Jazz',
            'classical' => 'Classical',
            'country' => 'Country',
            'folk' => 'Folk',
            'blues' => 'Blues',
            'metal' => 'Metal',
            'punk' => 'Punk',
            'reggae' => 'Reggae',
            'soul' => 'Soul',
            'disco' => 'Disco',
            'ambient' => 'Ambient',
            'lo-fi' => 'Lo-Fi',
            'synthwave' => 'Synthwave',
        ];
    }

    /**
     * Available moods
     */
    public static function getMoods(): array
    {
        return [
            'happy' => 'Happy',
            'sad' => 'Sad',
            'energetic' => 'Energetic',
            'calm' => 'Calm',
            'romantic' => 'Romantic',
            'angry' => 'Angry',
            'melancholic' => 'Melancholic',
            'uplifting' => 'Uplifting',
            'dark' => 'Dark',
            'peaceful' => 'Peaceful',
        ];
    }
}
