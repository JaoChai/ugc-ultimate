<?php

namespace App\Services;

use App\Models\ApiKey;

class SunoService
{
    protected KieApiService $kieApi;

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
     * @param string $prompt Description of the song/style
     * @param string|null $lyrics Custom lyrics (optional)
     * @param string|null $title Song title (optional)
     * @param string|null $style Music style/genre (optional)
     * @param bool $instrumental Generate instrumental only
     * @param string $model Suno model version (default: chirp-v3-5)
     */
    public function generate(
        string $prompt,
        ?string $lyrics = null,
        ?string $title = null,
        ?string $style = null,
        bool $instrumental = false,
        string $model = 'chirp-v3-5'
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
        string $model = 'chirp-v3-5'
    ): array {
        return $this->kieApi->post('/api/v1/suno/generate', [
            'prompt' => $prompt,
            'model' => $model,
            'auto_lyrics' => true,
        ]);
    }

    /**
     * Extend an existing song
     *
     * @param string $audioUrl URL of the audio to extend
     * @param string $prompt Extension prompt
     * @param int $continueAt Timestamp to continue from (seconds)
     */
    public function extend(
        string $audioUrl,
        string $prompt,
        int $continueAt = 0
    ): array {
        return $this->kieApi->post('/api/v1/suno/extend', [
            'audio_url' => $audioUrl,
            'prompt' => $prompt,
            'continue_at' => $continueAt,
        ]);
    }

    /**
     * Get task status
     */
    public function getTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/suno/task', ['task_id' => $taskId]);
    }

    /**
     * Wait for task completion
     */
    public function waitForCompletion(string $taskId, int $maxWaitSeconds = 300): array
    {
        $maxAttempts = $maxWaitSeconds / 5;

        return $this->kieApi->pollTaskStatus(
            '/api/v1/suno/task',
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
        bool $instrumental = false
    ): array {
        $result = $this->generate($prompt, $lyrics, $title, $style, $instrumental);

        $taskId = $result['task_id'] ?? null;

        if (!$taskId) {
            throw new \RuntimeException('No task ID returned from Suno API');
        }

        return $this->waitForCompletion($taskId);
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
