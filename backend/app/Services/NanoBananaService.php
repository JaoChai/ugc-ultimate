<?php

namespace App\Services;

use App\Models\ApiKey;

class NanoBananaService
{
    protected KieApiService $kieApi;

    // Available models
    public const MODEL_STANDARD = 'nano-banana';      // Gemini 2.5 Flash - fast, cheap
    public const MODEL_PRO = 'nano-banana-pro';       // Gemini 3 Pro - high quality

    // Available resolutions
    public const RESOLUTION_1K = '1K';
    public const RESOLUTION_2K = '2K';
    public const RESOLUTION_4K = '4K';

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
     * Generate image using Nano Banana API
     *
     * @param string $prompt Image description (max 10,000 chars)
     * @param array $imageInputs URLs of reference images (max 8)
     * @param string $aspectRatio 1:1, 2:3, 3:2, 4:3, 9:16, 16:9, 21:9, auto
     * @param string $resolution 1K, 2K, 4K
     * @param string $model nano-banana or nano-banana-pro
     * @param string $outputFormat PNG or JPG
     * @param string|null $callbackUrl Webhook URL for completion
     */
    public function generate(
        string $prompt,
        array $imageInputs = [],
        string $aspectRatio = '16:9',
        string $resolution = self::RESOLUTION_1K,
        string $model = self::MODEL_PRO,
        string $outputFormat = 'PNG',
        ?string $callbackUrl = null
    ): array {
        $payload = [
            'model' => $model,
            'input' => [
                'prompt' => $prompt,
                'aspect_ratio' => $aspectRatio,
                'resolution' => $resolution,
                'output_format' => $outputFormat,
            ],
        ];

        // Add reference images if provided (max 8)
        if (!empty($imageInputs)) {
            $payload['input']['image_input'] = array_slice($imageInputs, 0, 8);
        }

        // Add callback URL if provided
        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        return $this->kieApi->post('/api/v1/jobs/createTask', $payload);
    }

    /**
     * Generate with standard model (fast, cheap)
     */
    public function generateFast(
        string $prompt,
        string $aspectRatio = '16:9'
    ): array {
        return $this->generate(
            prompt: $prompt,
            aspectRatio: $aspectRatio,
            resolution: self::RESOLUTION_1K,
            model: self::MODEL_STANDARD
        );
    }

    /**
     * Generate with pro model (high quality, 4K)
     */
    public function generatePro(
        string $prompt,
        string $aspectRatio = '16:9',
        string $resolution = self::RESOLUTION_4K
    ): array {
        return $this->generate(
            prompt: $prompt,
            aspectRatio: $aspectRatio,
            resolution: $resolution,
            model: self::MODEL_PRO
        );
    }

    /**
     * Edit/transform existing images
     *
     * @param array $imageUrls Source image URLs to transform
     * @param string $prompt Edit instructions
     */
    public function edit(
        array $imageUrls,
        string $prompt,
        string $model = self::MODEL_PRO
    ): array {
        return $this->generate(
            prompt: $prompt,
            imageInputs: $imageUrls,
            model: $model
        );
    }

    /**
     * Get task status
     */
    public function getTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/jobs/getTask', ['taskId' => $taskId]);
    }

    /**
     * Wait for task completion
     */
    public function waitForCompletion(string $taskId, int $maxWaitSeconds = 300): array
    {
        $maxAttempts = $maxWaitSeconds / 5;

        return $this->kieApi->pollTaskStatus(
            '/api/v1/jobs/getTask',
            $taskId,
            (int) $maxAttempts,
            5
        );
    }

    /**
     * Generate and wait for completion (synchronous)
     */
    public function generateAndWait(
        string $prompt,
        string $aspectRatio = '16:9',
        string $resolution = self::RESOLUTION_1K,
        string $model = self::MODEL_PRO
    ): array {
        $result = $this->generate($prompt, [], $aspectRatio, $resolution, $model);

        $taskId = $result['taskId'] ?? $result['task_id'] ?? null;

        if (!$taskId) {
            throw new \RuntimeException('No task ID returned from Nano Banana API');
        }

        return $this->waitForCompletion($taskId);
    }

    /**
     * Available models
     */
    public static function getModels(): array
    {
        return [
            self::MODEL_STANDARD => [
                'name' => 'Nano Banana',
                'description' => 'Gemini 2.5 Flash - Fast & cheap (~$0.02/image)',
                'engine' => 'gemini-2.5-flash',
            ],
            self::MODEL_PRO => [
                'name' => 'Nano Banana Pro',
                'description' => 'Gemini 3 Pro - High quality, 4K ($0.09-0.12/image)',
                'engine' => 'gemini-3-pro',
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
            '2:3' => 'Portrait (2:3)',
            '3:2' => 'Landscape (3:2)',
            '4:3' => 'Standard (4:3)',
            '9:16' => 'Vertical (9:16)',
            '16:9' => 'Widescreen (16:9)',
            '21:9' => 'Ultrawide (21:9)',
            'auto' => 'Auto',
        ];
    }

    /**
     * Available resolutions
     */
    public static function getResolutions(): array
    {
        return [
            self::RESOLUTION_1K => '1K (1024px)',
            self::RESOLUTION_2K => '2K (2048px)',
            self::RESOLUTION_4K => '4K (4096px)',
        ];
    }
}
