<?php

namespace App\Services;

use App\Models\ApiKey;

class VideoService
{
    protected KieApiService $kieApi;

    // Available video generation providers
    public const PROVIDER_KLING = 'kling';
    public const PROVIDER_HAILUO = 'hailuo';
    public const PROVIDER_RUNWAY = 'runway';

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
     * Generate video from text prompt using Kling
     *
     * @param string $prompt Video description
     * @param string $aspectRatio 16:9, 9:16, 1:1
     * @param int $duration Duration in seconds (5 or 10)
     * @param string $mode standard or professional
     */
    public function generateWithKling(
        string $prompt,
        string $aspectRatio = '16:9',
        int $duration = 5,
        string $mode = 'standard'
    ): array {
        return $this->kieApi->post('/api/v1/kling/text-to-video', [
            'prompt' => $prompt,
            'aspect_ratio' => $aspectRatio,
            'duration' => $duration,
            'mode' => $mode,
        ]);
    }

    /**
     * Generate video from image using Kling (Image-to-Video)
     *
     * @param string $imageUrl URL of the source image
     * @param string $prompt Motion/animation description
     * @param int $duration Duration in seconds (5 or 10)
     */
    public function imageToVideoWithKling(
        string $imageUrl,
        string $prompt,
        int $duration = 5
    ): array {
        return $this->kieApi->post('/api/v1/kling/image-to-video', [
            'image_url' => $imageUrl,
            'prompt' => $prompt,
            'duration' => $duration,
        ]);
    }

    /**
     * Generate video using Hailuo (MiniMax)
     *
     * @param string $prompt Video description
     * @param string|null $imageUrl Optional starting image
     */
    public function generateWithHailuo(
        string $prompt,
        ?string $imageUrl = null
    ): array {
        $payload = ['prompt' => $prompt];

        if ($imageUrl) {
            $payload['image_url'] = $imageUrl;
        }

        return $this->kieApi->post('/api/v1/hailuo/generate', $payload);
    }

    /**
     * Generate video using Runway Gen-3
     *
     * @param string $prompt Video description
     * @param string|null $imageUrl Optional starting image
     * @param int $duration Duration in seconds (5 or 10)
     */
    public function generateWithRunway(
        string $prompt,
        ?string $imageUrl = null,
        int $duration = 5
    ): array {
        $payload = [
            'prompt' => $prompt,
            'duration' => $duration,
        ];

        if ($imageUrl) {
            $payload['image_url'] = $imageUrl;
        }

        return $this->kieApi->post('/api/v1/runway/generate', $payload);
    }

    /**
     * Get Kling task status
     */
    public function getKlingTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/kling/task', ['task_id' => $taskId]);
    }

    /**
     * Get Hailuo task status
     */
    public function getHailuoTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/hailuo/task', ['task_id' => $taskId]);
    }

    /**
     * Get Runway task status
     */
    public function getRunwayTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/runway/task', ['task_id' => $taskId]);
    }

    /**
     * Wait for video generation to complete
     */
    public function waitForCompletion(
        string $provider,
        string $taskId,
        int $maxWaitSeconds = 600
    ): array {
        $endpoint = match ($provider) {
            self::PROVIDER_KLING => '/api/v1/kling/task',
            self::PROVIDER_HAILUO => '/api/v1/hailuo/task',
            self::PROVIDER_RUNWAY => '/api/v1/runway/task',
            default => throw new \InvalidArgumentException('Invalid provider'),
        };

        $maxAttempts = $maxWaitSeconds / 10; // Check every 10 seconds

        return $this->kieApi->pollTaskStatus(
            $endpoint,
            $taskId,
            (int) $maxAttempts,
            10
        );
    }

    /**
     * Generate video using the best available provider
     */
    public function generate(
        string $prompt,
        ?string $imageUrl = null,
        string $provider = self::PROVIDER_KLING,
        array $options = []
    ): array {
        return match ($provider) {
            self::PROVIDER_KLING => $imageUrl
                ? $this->imageToVideoWithKling(
                    $imageUrl,
                    $prompt,
                    $options['duration'] ?? 5
                )
                : $this->generateWithKling(
                    $prompt,
                    $options['aspect_ratio'] ?? '16:9',
                    $options['duration'] ?? 5,
                    $options['mode'] ?? 'standard'
                ),
            self::PROVIDER_HAILUO => $this->generateWithHailuo($prompt, $imageUrl),
            self::PROVIDER_RUNWAY => $this->generateWithRunway(
                $prompt,
                $imageUrl,
                $options['duration'] ?? 5
            ),
            default => throw new \InvalidArgumentException('Invalid provider: ' . $provider),
        };
    }

    /**
     * Available video providers
     */
    public static function getProviders(): array
    {
        return [
            self::PROVIDER_KLING => [
                'name' => 'Kling AI',
                'description' => 'High quality video generation',
                'max_duration' => 10,
                'supports_image' => true,
            ],
            self::PROVIDER_HAILUO => [
                'name' => 'Hailuo (MiniMax)',
                'description' => 'Fast video generation',
                'max_duration' => 6,
                'supports_image' => true,
            ],
            self::PROVIDER_RUNWAY => [
                'name' => 'Runway Gen-3',
                'description' => 'Professional video generation',
                'max_duration' => 10,
                'supports_image' => true,
            ],
        ];
    }

    /**
     * Available aspect ratios
     */
    public static function getAspectRatios(): array
    {
        return [
            '16:9' => 'Landscape (16:9)',
            '9:16' => 'Portrait (9:16)',
            '1:1' => 'Square (1:1)',
        ];
    }
}
