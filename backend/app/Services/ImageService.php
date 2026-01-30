<?php

namespace App\Services;

use App\Models\ApiKey;

class ImageService
{
    protected NanoBananaService $nanoBanana;

    // Available image generation providers (Nano Banana only)
    public const PROVIDER_NANO_BANANA = 'nano-banana';
    public const PROVIDER_NANO_BANANA_PRO = 'nano-banana-pro';

    public function __construct(NanoBananaService $nanoBanana)
    {
        $this->nanoBanana = $nanoBanana;
    }

    public function setApiKey(ApiKey $apiKey): self
    {
        $this->nanoBanana->setApiKey($apiKey);
        return $this;
    }

    /**
     * Generate image using Nano Banana
     *
     * @param string $prompt Image description
     * @param string $provider nano-banana or nano-banana-pro
     * @param array $options Additional options (aspect_ratio, resolution, image_inputs)
     */
    public function generate(
        string $prompt,
        string $provider = self::PROVIDER_NANO_BANANA_PRO,
        array $options = []
    ): array {
        $model = match ($provider) {
            self::PROVIDER_NANO_BANANA => NanoBananaService::MODEL_STANDARD,
            self::PROVIDER_NANO_BANANA_PRO => NanoBananaService::MODEL_PRO,
            default => NanoBananaService::MODEL_PRO,
        };

        return $this->nanoBanana->generate(
            prompt: $prompt,
            imageInputs: $options['image_inputs'] ?? [],
            aspectRatio: $options['aspect_ratio'] ?? '16:9',
            resolution: $options['resolution'] ?? NanoBananaService::RESOLUTION_1K,
            model: $model
        );
    }

    /**
     * Generate fast (standard model)
     */
    public function generateFast(string $prompt, string $aspectRatio = '16:9'): array
    {
        return $this->nanoBanana->generateStandard($prompt, $aspectRatio);
    }

    /**
     * Generate high quality (pro model, 4K)
     */
    public function generatePro(
        string $prompt,
        string $aspectRatio = '16:9',
        string $resolution = NanoBananaService::RESOLUTION_4K
    ): array {
        return $this->nanoBanana->generatePro($prompt, [], $aspectRatio, $resolution);
    }

    /**
     * Edit/transform existing images
     */
    public function edit(array $imageUrls, string $prompt): array
    {
        return $this->nanoBanana->edit($prompt, $imageUrls);
    }

    /**
     * Get task status
     */
    public function getTaskStatus(string $taskId): array
    {
        return $this->nanoBanana->getTaskStatus($taskId);
    }

    /**
     * Wait for image generation to complete
     */
    public function waitForCompletion(string $taskId, int $maxWaitSeconds = 300): array
    {
        return $this->nanoBanana->waitForCompletion($taskId, $maxWaitSeconds);
    }

    /**
     * Available image providers
     */
    public static function getProviders(): array
    {
        return [
            self::PROVIDER_NANO_BANANA => [
                'name' => 'Nano Banana',
                'description' => 'Fast & cheap (~$0.02/image)',
                'models' => [NanoBananaService::MODEL_STANDARD],
            ],
            self::PROVIDER_NANO_BANANA_PRO => [
                'name' => 'Nano Banana Pro',
                'description' => 'High quality, 4K ($0.09-0.12/image)',
                'models' => [NanoBananaService::MODEL_PRO],
            ],
        ];
    }

    /**
     * Available aspect ratios
     */
    public static function getAspectRatios(): array
    {
        return NanoBananaService::getAspectRatios();
    }

    /**
     * Available resolutions
     */
    public static function getResolutions(): array
    {
        return NanoBananaService::getResolutions();
    }
}
