<?php

namespace App\Services;

use App\Models\ApiKey;

class ImageService
{
    protected KieApiService $kieApi;

    // Available image generation providers
    public const PROVIDER_FLUX = 'flux';
    public const PROVIDER_MIDJOURNEY = 'midjourney';
    public const PROVIDER_DALLE = 'dalle';
    public const PROVIDER_IDEOGRAM = 'ideogram';

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
     * Generate image using Flux
     *
     * @param string $prompt Image description
     * @param string $model flux-dev, flux-pro, flux-schnell
     * @param string $aspectRatio 1:1, 16:9, 9:16, 4:3, 3:4
     */
    public function generateWithFlux(
        string $prompt,
        string $model = 'flux-dev',
        string $aspectRatio = '16:9'
    ): array {
        return $this->kieApi->post('/api/v1/flux/generate', [
            'prompt' => $prompt,
            'model' => $model,
            'aspect_ratio' => $aspectRatio,
        ]);
    }

    /**
     * Generate image using Midjourney
     *
     * @param string $prompt Image description
     * @param string $aspectRatio --ar parameter
     * @param string|null $style --style parameter
     */
    public function generateWithMidjourney(
        string $prompt,
        string $aspectRatio = '16:9',
        ?string $style = null
    ): array {
        $fullPrompt = $prompt . ' --ar ' . $aspectRatio;

        if ($style) {
            $fullPrompt .= ' --style ' . $style;
        }

        return $this->kieApi->post('/api/v1/midjourney/imagine', [
            'prompt' => $fullPrompt,
        ]);
    }

    /**
     * Generate image using DALL-E 3
     *
     * @param string $prompt Image description
     * @param string $size 1024x1024, 1792x1024, 1024x1792
     * @param string $quality standard, hd
     */
    public function generateWithDalle(
        string $prompt,
        string $size = '1792x1024',
        string $quality = 'standard'
    ): array {
        return $this->kieApi->post('/api/v1/dalle/generate', [
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
        ]);
    }

    /**
     * Generate image using Ideogram
     *
     * @param string $prompt Image description
     * @param string $aspectRatio 1:1, 16:9, 9:16
     * @param string $style auto, realistic, design, anime, render_3d
     */
    public function generateWithIdeogram(
        string $prompt,
        string $aspectRatio = '16:9',
        string $style = 'auto'
    ): array {
        return $this->kieApi->post('/api/v1/ideogram/generate', [
            'prompt' => $prompt,
            'aspect_ratio' => $aspectRatio,
            'style' => $style,
        ]);
    }

    /**
     * Get Flux task status
     */
    public function getFluxTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/flux/task', ['task_id' => $taskId]);
    }

    /**
     * Get Midjourney task status
     */
    public function getMidjourneyTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/midjourney/task', ['task_id' => $taskId]);
    }

    /**
     * Wait for image generation to complete
     */
    public function waitForCompletion(
        string $provider,
        string $taskId,
        int $maxWaitSeconds = 300
    ): array {
        $endpoint = match ($provider) {
            self::PROVIDER_FLUX => '/api/v1/flux/task',
            self::PROVIDER_MIDJOURNEY => '/api/v1/midjourney/task',
            self::PROVIDER_DALLE => '/api/v1/dalle/task',
            self::PROVIDER_IDEOGRAM => '/api/v1/ideogram/task',
            default => throw new \InvalidArgumentException('Invalid provider'),
        };

        $maxAttempts = $maxWaitSeconds / 5; // Check every 5 seconds

        return $this->kieApi->pollTaskStatus(
            $endpoint,
            $taskId,
            (int) $maxAttempts,
            5
        );
    }

    /**
     * Generate image using the best available provider
     */
    public function generate(
        string $prompt,
        string $provider = self::PROVIDER_FLUX,
        array $options = []
    ): array {
        return match ($provider) {
            self::PROVIDER_FLUX => $this->generateWithFlux(
                $prompt,
                $options['model'] ?? 'flux-dev',
                $options['aspect_ratio'] ?? '16:9'
            ),
            self::PROVIDER_MIDJOURNEY => $this->generateWithMidjourney(
                $prompt,
                $options['aspect_ratio'] ?? '16:9',
                $options['style'] ?? null
            ),
            self::PROVIDER_DALLE => $this->generateWithDalle(
                $prompt,
                $options['size'] ?? '1792x1024',
                $options['quality'] ?? 'standard'
            ),
            self::PROVIDER_IDEOGRAM => $this->generateWithIdeogram(
                $prompt,
                $options['aspect_ratio'] ?? '16:9',
                $options['style'] ?? 'auto'
            ),
            default => throw new \InvalidArgumentException('Invalid provider: ' . $provider),
        };
    }

    /**
     * Available image providers
     */
    public static function getProviders(): array
    {
        return [
            self::PROVIDER_FLUX => [
                'name' => 'Flux',
                'description' => 'Fast and high quality',
                'models' => ['flux-schnell', 'flux-dev', 'flux-pro'],
            ],
            self::PROVIDER_MIDJOURNEY => [
                'name' => 'Midjourney',
                'description' => 'Artistic and creative',
                'models' => ['v6', 'niji'],
            ],
            self::PROVIDER_DALLE => [
                'name' => 'DALL-E 3',
                'description' => 'OpenAI image generation',
                'models' => ['dall-e-3'],
            ],
            self::PROVIDER_IDEOGRAM => [
                'name' => 'Ideogram',
                'description' => 'Great for text in images',
                'models' => ['ideogram-v2'],
            ],
        ];
    }

    /**
     * Available aspect ratios
     */
    public static function getAspectRatios(): array
    {
        return [
            '1:1' => 'Square (1:1)',
            '16:9' => 'Landscape (16:9)',
            '9:16' => 'Portrait (9:16)',
            '4:3' => 'Standard (4:3)',
            '3:4' => 'Portrait (3:4)',
        ];
    }
}
