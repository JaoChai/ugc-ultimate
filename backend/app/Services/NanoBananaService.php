<?php

namespace App\Services;

use App\Models\ApiKey;

/**
 * Nano Banana Image Generation Service
 *
 * Provides integration with kie.ai's Nano Banana image generation APIs:
 * - google/nano-banana: Fast text-to-image (Gemini 2.5 Flash)
 * - nano-banana-pro: High quality with resolution options (Gemini 3 Pro)
 * - google/nano-banana-edit: Image editing capabilities
 *
 * @see https://docs.kie.ai/market/google/nano-banana
 * @see https://docs.kie.ai/market/google/pro-image-to-image
 * @see https://docs.kie.ai/market/google/nano-banana-edit
 */
class NanoBananaService
{
    protected KieApiService $kieApi;

    // Model names (exact values from docs)
    public const MODEL_STANDARD = 'google/nano-banana';
    public const MODEL_PRO = 'nano-banana-pro';
    public const MODEL_EDIT = 'google/nano-banana-edit';

    // Available resolutions (Pro model only)
    public const RESOLUTION_1K = '1K';
    public const RESOLUTION_2K = '2K';
    public const RESOLUTION_4K = '4K';

    // Output formats
    public const FORMAT_PNG = 'png';
    public const FORMAT_JPG = 'jpg';
    public const FORMAT_JPEG = 'jpeg';

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
     * Generate image using Standard model (google/nano-banana)
     *
     * Fast generation using Gemini 2.5 Flash (~$0.02/image)
     *
     * @param string $prompt Image description (max 5,000 chars)
     * @param string $imageSize Aspect ratio: 1:1, 9:16, 16:9, 3:4, 4:3, 3:2, 2:3, 5:4, 4:5, 21:9, auto
     * @param string $outputFormat png or jpeg
     * @param string|null $callbackUrl Webhook URL for completion
     */
    public function generateStandard(
        string $prompt,
        string $imageSize = '16:9',
        string $outputFormat = self::FORMAT_PNG,
        ?string $callbackUrl = null
    ): array {
        $payload = [
            'model' => self::MODEL_STANDARD,
            'input' => [
                'prompt' => $prompt,
                'image_size' => $imageSize,
                'output_format' => $outputFormat,
            ],
        ];

        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        return $this->kieApi->post('/api/v1/jobs/createTask', $payload);
    }

    /**
     * Generate image using Pro model (nano-banana-pro)
     *
     * High quality generation with resolution control using Gemini 3 Pro
     *
     * @param string $prompt Image description (max 10,000 chars)
     * @param array $imageInput URLs of reference images (max 8, JPEG/PNG/WebP, max 30MB each)
     * @param string $aspectRatio 1:1, 2:3, 3:2, 3:4, 4:3, 4:5, 5:4, 9:16, 16:9, 21:9, auto
     * @param string $resolution 1K, 2K, or 4K
     * @param string $outputFormat png or jpg
     * @param string|null $callbackUrl Webhook URL for completion
     */
    public function generatePro(
        string $prompt,
        array $imageInput = [],
        string $aspectRatio = '16:9',
        string $resolution = self::RESOLUTION_1K,
        string $outputFormat = self::FORMAT_PNG,
        ?string $callbackUrl = null
    ): array {
        $payload = [
            'model' => self::MODEL_PRO,
            'input' => [
                'prompt' => $prompt,
                'aspect_ratio' => $aspectRatio,
                'resolution' => $resolution,
                'output_format' => $outputFormat,
            ],
        ];

        // Add reference images if provided (max 8)
        if (!empty($imageInput)) {
            $payload['input']['image_input'] = array_slice($imageInput, 0, 8);
        }

        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        return $this->kieApi->post('/api/v1/jobs/createTask', $payload);
    }

    /**
     * Edit images using Edit model (google/nano-banana-edit)
     *
     * @param string $prompt Edit instructions (max 5,000 chars)
     * @param array $imageUrls Source image URLs to edit (max 10, JPEG/PNG/WebP, max 10MB each)
     * @param string $imageSize Output aspect ratio
     * @param string $outputFormat png or jpeg
     * @param string|null $callbackUrl Webhook URL for completion
     */
    public function edit(
        string $prompt,
        array $imageUrls,
        string $imageSize = '1:1',
        string $outputFormat = self::FORMAT_PNG,
        ?string $callbackUrl = null
    ): array {
        $payload = [
            'model' => self::MODEL_EDIT,
            'input' => [
                'prompt' => $prompt,
                'image_urls' => array_slice($imageUrls, 0, 10),
                'image_size' => $imageSize,
                'output_format' => $outputFormat,
            ],
        ];

        if ($callbackUrl) {
            $payload['callBackUrl'] = $callbackUrl;
        }

        return $this->kieApi->post('/api/v1/jobs/createTask', $payload);
    }

    /**
     * Alias for generateStandard - backward compatibility
     */
    public function generate(
        string $prompt,
        array $imageInputs = [],
        string $aspectRatio = '16:9',
        string $resolution = self::RESOLUTION_1K,
        string $model = self::MODEL_PRO,
        string $outputFormat = self::FORMAT_PNG,
        ?string $callbackUrl = null
    ): array {
        // Route to appropriate method based on model
        if ($model === self::MODEL_STANDARD) {
            return $this->generateStandard($prompt, $aspectRatio, $outputFormat, $callbackUrl);
        }

        return $this->generatePro($prompt, $imageInputs, $aspectRatio, $resolution, $outputFormat, $callbackUrl);
    }

    /**
     * Get task status
     *
     * @param string $taskId Task ID from generate response
     */
    public function getTaskStatus(string $taskId): array
    {
        return $this->kieApi->get('/api/v1/jobs/getTask', ['taskId' => $taskId]);
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
            '/api/v1/jobs/getTask',
            $taskId,
            $maxAttempts,
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
        $result = $this->generate(
            prompt: $prompt,
            aspectRatio: $aspectRatio,
            resolution: $resolution,
            model: $model
        );

        $taskId = $result['taskId'] ?? null;

        if (!$taskId) {
            throw new \RuntimeException('No task ID returned from Nano Banana API');
        }

        return $this->waitForCompletion($taskId);
    }

    /**
     * Available models with descriptions
     */
    public static function getModels(): array
    {
        return [
            self::MODEL_STANDARD => [
                'name' => 'Nano Banana',
                'description' => 'Fast generation - Gemini 2.5 Flash (~$0.02/image)',
                'engine' => 'gemini-2.5-flash',
                'max_prompt_chars' => 5000,
                'supports_resolution' => false,
                'supports_image_input' => false,
            ],
            self::MODEL_PRO => [
                'name' => 'Nano Banana Pro',
                'description' => 'High quality - Gemini 3 Pro with 4K support ($0.09-0.12/image)',
                'engine' => 'gemini-3-pro',
                'max_prompt_chars' => 10000,
                'supports_resolution' => true,
                'supports_image_input' => true,
            ],
            self::MODEL_EDIT => [
                'name' => 'Nano Banana Edit',
                'description' => 'Image editing and transformation',
                'engine' => 'gemini-2.5-flash',
                'max_prompt_chars' => 5000,
                'supports_resolution' => false,
                'supports_image_input' => true,
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
            '3:4' => 'Portrait (3:4)',
            '4:3' => 'Standard (4:3)',
            '4:5' => 'Portrait (4:5)',
            '5:4' => 'Landscape (5:4)',
            '9:16' => 'Vertical (9:16)',
            '16:9' => 'Widescreen (16:9)',
            '21:9' => 'Ultrawide (21:9)',
            'auto' => 'Auto',
        ];
    }

    /**
     * Available resolutions (Pro model only)
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
