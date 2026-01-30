<?php

namespace App\Services;

use App\Models\ApiKey;

/**
 * Suno Music Generation Service
 *
 * Provides integration with kie.ai's Suno music generation APIs:
 * - Music generation with customizable styles
 * - Lyrics generation
 * - Music extension
 * - Stem separation (vocals/instrumentals)
 * - MIDI conversion
 * - Cover image generation
 *
 * @see https://docs.kie.ai/suno-api/quickstart
 * @see https://docs.kie.ai/suno-api/generate-music
 */
class SunoService
{
    protected KieApiService $kieApi;

    // Model names (exact values from docs)
    public const MODEL_V4 = 'V4';           // Improved vocals, max 4 min
    public const MODEL_V4_5 = 'V4_5';       // Smart prompts, max 8 min
    public const MODEL_V4_5_PLUS = 'V4_5PLUS';  // Richer sound, max 8 min
    public const MODEL_V4_5_ALL = 'V4_5ALL';    // Enhanced prompting, max 8 min
    public const MODEL_V5 = 'V5';           // Superior expression, faster

    // Default model
    public const DEFAULT_MODEL = self::MODEL_V5;

    // Stem separation types
    public const STEM_VOCAL = 'separate_vocal';  // 2 stems: vocals + instrumental
    public const STEM_SPLIT = 'split_stem';      // Up to 12 stems

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
     * Generate music
     *
     * @param string $prompt Content description (lyrics if customMode=true and instrumental=false)
     * @param string $model V4, V4_5, V4_5PLUS, V4_5ALL, or V5
     * @param bool $customMode Enable advanced customization
     * @param bool $instrumental Generate without vocals
     * @param string|null $style Music style/genre (required if customMode=true)
     * @param string|null $title Track name (max 80-100 chars depending on model)
     * @param string $callbackUrl Webhook URL for completion (required)
     * @param array $options Additional options: negativeTags, vocalGender, styleWeight, etc.
     */
    public function generate(
        string $prompt,
        string $model = self::DEFAULT_MODEL,
        bool $customMode = false,
        bool $instrumental = false,
        ?string $style = null,
        ?string $title = null,
        string $callbackUrl = '',
        array $options = []
    ): array {
        $payload = [
            'prompt' => $prompt,
            'model' => $model,
            'customMode' => $customMode,
            'instrumental' => $instrumental,
        ];

        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        if ($style) {
            $payload['style'] = $style;
        }

        if ($title) {
            $payload['title'] = $title;
        }

        // Add optional parameters
        $optionalParams = ['negativeTags', 'vocalGender', 'styleWeight', 'weirdnessConstraint', 'audioWeight', 'personaId'];
        foreach ($optionalParams as $param) {
            if (isset($options[$param])) {
                $payload[$param] = $options[$param];
            }
        }

        return $this->kieApi->post('/api/v1/generate', $payload);
    }

    /**
     * Generate music with auto-generated lyrics
     */
    public function generateAuto(
        string $prompt,
        string $model = self::DEFAULT_MODEL,
        string $callbackUrl = ''
    ): array {
        return $this->generate(
            prompt: $prompt,
            model: $model,
            customMode: false,
            instrumental: false,
            callbackUrl: $callbackUrl
        );
    }

    /**
     * Generate instrumental music (no vocals)
     */
    public function generateInstrumental(
        string $prompt,
        string $model = self::DEFAULT_MODEL,
        ?string $style = null,
        string $callbackUrl = ''
    ): array {
        return $this->generate(
            prompt: $prompt,
            model: $model,
            customMode: $style !== null,
            instrumental: true,
            style: $style,
            callbackUrl: $callbackUrl
        );
    }

    /**
     * Generate lyrics from a prompt
     *
     * @param string $prompt Description of desired lyrics (max 200 words)
     * @param string $callbackUrl Webhook URL for completion (required)
     */
    public function generateLyrics(string $prompt, string $callbackUrl = ''): array
    {
        $payload = [
            'prompt' => $prompt,
        ];

        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        return $this->kieApi->post('/api/v1/lyrics', $payload);
    }

    /**
     * Extend an existing song
     *
     * @param string $audioId Audio ID from previous generation
     * @param string $prompt Extension prompt
     * @param string $model AI model version
     * @param bool $defaultParamFlag true=use custom params, false=use original audio params
     * @param int|null $continueAt Start time in seconds (required if defaultParamFlag=true)
     * @param string|null $style Music style (required if defaultParamFlag=true)
     * @param string|null $title Track title (required if defaultParamFlag=true)
     * @param string $callbackUrl Webhook URL for completion (required)
     */
    public function extend(
        string $audioId,
        string $prompt,
        string $model = self::DEFAULT_MODEL,
        bool $defaultParamFlag = false,
        ?int $continueAt = null,
        ?string $style = null,
        ?string $title = null,
        string $callbackUrl = ''
    ): array {
        $payload = [
            'audioId' => $audioId,
            'prompt' => $prompt,
            'model' => $model,
            'defaultParamFlag' => $defaultParamFlag,
        ];

        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        if ($defaultParamFlag) {
            if ($continueAt !== null) {
                $payload['continueAt'] = $continueAt;
            }
            if ($style) {
                $payload['style'] = $style;
            }
            if ($title) {
                $payload['title'] = $title;
            }
        }

        return $this->kieApi->post('/api/v1/generate/extend', $payload);
    }

    /**
     * Separate vocals from instrumentals (stem separation)
     *
     * @param string $taskId Task ID from generate or extend
     * @param string $audioId Audio ID to process
     * @param string $type separate_vocal (2 stems) or split_stem (12 stems)
     * @param string $callbackUrl Webhook URL for completion (required)
     */
    public function extractStems(
        string $taskId,
        string $audioId,
        string $type = self::STEM_VOCAL,
        string $callbackUrl = ''
    ): array {
        $payload = [
            'taskId' => $taskId,
            'audioId' => $audioId,
            'type' => $type,
        ];

        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        return $this->kieApi->post('/api/v1/vocal-removal/generate', $payload);
    }

    /**
     * Convert audio to MIDI
     *
     * @param string $taskId Task ID from stem separation
     * @param string $callbackUrl Webhook URL for completion (required)
     * @param string|null $audioId Specific audio ID (optional, processes all if omitted)
     */
    public function convertToMidi(
        string $taskId,
        string $callbackUrl = '',
        ?string $audioId = null
    ): array {
        $payload = [
            'taskId' => $taskId,
        ];

        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        if ($audioId) {
            $payload['audioId'] = $audioId;
        }

        return $this->kieApi->post('/api/v1/midi/generate', $payload);
    }

    /**
     * Generate cover image for a track
     *
     * @param string $taskId Task ID from music generation
     * @param string $callbackUrl Webhook URL for completion (required)
     */
    public function generateCover(string $taskId, string $callbackUrl = ''): array
    {
        $payload = [
            'taskId' => $taskId,
        ];

        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        return $this->kieApi->post('/api/v1/suno/cover/generate', $payload);
    }

    /**
     * Get music task status/details
     *
     * @param string $taskId Task ID from generate response
     */
    public function getTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/generate/record-info', ['taskId' => $taskId]);
    }

    /**
     * Wait for task completion (polling)
     *
     * @param string $taskId Task ID
     * @param int $maxWaitSeconds Maximum wait time
     */
    public function waitForCompletion(string $taskId, int $maxWaitSeconds = 300): array
    {
        $maxAttempts = (int) ($maxWaitSeconds / 5);

        return $this->kieApi->pollTaskStatus(
            '/api/v1/generate/record-info',
            $taskId,
            $maxAttempts,
            5
        );
    }

    /**
     * Generate music and wait for completion (synchronous)
     */
    public function generateAndWait(
        string $prompt,
        string $model = self::DEFAULT_MODEL,
        bool $customMode = false,
        bool $instrumental = false,
        ?string $style = null,
        ?string $title = null
    ): array {
        $result = $this->generate(
            prompt: $prompt,
            model: $model,
            customMode: $customMode,
            instrumental: $instrumental,
            style: $style,
            title: $title
        );

        $taskId = $result['taskId'] ?? null;

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
            self::MODEL_V4 => [
                'name' => 'Suno V4',
                'description' => 'Improved vocal quality',
                'max_duration' => 240, // 4 minutes
                'max_prompt_chars' => 3000,
                'max_style_chars' => 200,
                'max_title_chars' => 80,
            ],
            self::MODEL_V4_5 => [
                'name' => 'Suno V4.5',
                'description' => 'Smart prompts, faster generation',
                'max_duration' => 480, // 8 minutes
                'max_prompt_chars' => 5000,
                'max_style_chars' => 1000,
                'max_title_chars' => 100,
            ],
            self::MODEL_V4_5_PLUS => [
                'name' => 'Suno V4.5+',
                'description' => 'Richer sound quality',
                'max_duration' => 480,
                'max_prompt_chars' => 5000,
                'max_style_chars' => 1000,
                'max_title_chars' => 100,
            ],
            self::MODEL_V4_5_ALL => [
                'name' => 'Suno V4.5 ALL',
                'description' => 'Enhanced prompting',
                'max_duration' => 480,
                'max_prompt_chars' => 5000,
                'max_style_chars' => 1000,
                'max_title_chars' => 80,
            ],
            self::MODEL_V5 => [
                'name' => 'Suno V5',
                'description' => 'Superior expression, fastest generation',
                'max_duration' => 480,
                'max_prompt_chars' => 5000,
                'max_style_chars' => 1000,
                'max_title_chars' => 100,
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

    /**
     * Stem separation types
     */
    public static function getStemTypes(): array
    {
        return [
            self::STEM_VOCAL => [
                'name' => 'Vocal Separation',
                'description' => '2 stems: vocals + instrumental',
                'stems' => ['vocal', 'instrumental'],
            ],
            self::STEM_SPLIT => [
                'name' => 'Full Stem Split',
                'description' => 'Up to 12 individual instrument stems',
                'stems' => [
                    'vocals', 'backing_vocals', 'drums', 'bass', 'guitar',
                    'keyboard', 'strings', 'brass', 'woodwinds', 'percussion',
                    'synth', 'fx',
                ],
            ],
        ];
    }
}
