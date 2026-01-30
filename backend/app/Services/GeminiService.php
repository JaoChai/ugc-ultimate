<?php

namespace App\Services;

use App\Models\ApiKey;

class GeminiService
{
    protected KieApiService $kieApi;

    // Thinking levels for Gemini 3 Flash
    public const THINKING_NONE = 'none';
    public const THINKING_LOW = 'low';
    public const THINKING_MEDIUM = 'medium';
    public const THINKING_HIGH = 'high';

    // Available models
    public const MODEL_GEMINI_FLASH = 'gemini-2.0-flash';
    public const MODEL_GEMINI_PRO = 'gemini-pro';

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
     * Generate text completion using Gemini
     *
     * @param string $prompt The prompt to send
     * @param string $model Model to use
     * @param string $thinkingLevel Thinking level (none, low, medium, high)
     * @param float $temperature Temperature (0.0 - 2.0)
     * @param int $maxTokens Max output tokens
     */
    public function generate(
        string $prompt,
        string $model = self::MODEL_GEMINI_FLASH,
        string $thinkingLevel = self::THINKING_MEDIUM,
        float $temperature = 0.7,
        int $maxTokens = 4096
    ): array {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        // Add thinking configuration for Gemini 3 Flash
        if ($thinkingLevel !== self::THINKING_NONE) {
            $payload['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => $this->getThinkingBudget($thinkingLevel),
            ];
        }

        return $this->kieApi->post('/api/v1/gemini/chat', $payload);
    }

    /**
     * Generate with system prompt
     */
    public function generateWithSystem(
        string $systemPrompt,
        string $userPrompt,
        string $model = self::MODEL_GEMINI_FLASH,
        string $thinkingLevel = self::THINKING_MEDIUM,
        float $temperature = 0.7
    ): array {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $temperature,
        ];

        if ($thinkingLevel !== self::THINKING_NONE) {
            $payload['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => $this->getThinkingBudget($thinkingLevel),
            ];
        }

        return $this->kieApi->post('/api/v1/gemini/chat', $payload);
    }

    /**
     * Generate structured JSON output
     */
    public function generateJson(
        string $prompt,
        array $schema,
        string $model = self::MODEL_GEMINI_FLASH,
        string $thinkingLevel = self::THINKING_MEDIUM
    ): array {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => $schema,
            ],
        ];

        if ($thinkingLevel !== self::THINKING_NONE) {
            $payload['thinking'] = [
                'type' => 'enabled',
                'budget_tokens' => $this->getThinkingBudget($thinkingLevel),
            ];
        }

        $result = $this->kieApi->post('/api/v1/gemini/chat', $payload);

        // Parse JSON from response
        $content = $result['choices'][0]['message']['content'] ?? '';

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Try to extract JSON from markdown code block
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
                return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            }
            throw new \RuntimeException('Failed to parse JSON response: ' . $e->getMessage());
        }
    }

    /**
     * Multimodal: analyze image and generate text
     */
    public function analyzeImage(
        string $imageUrl,
        string $prompt,
        string $model = self::MODEL_GEMINI_FLASH
    ): array {
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                    ],
                ],
            ],
        ];

        return $this->kieApi->post('/api/v1/gemini/chat', $payload);
    }

    /**
     * Get thinking budget based on level
     */
    protected function getThinkingBudget(string $level): int
    {
        return match ($level) {
            self::THINKING_LOW => 1024,
            self::THINKING_MEDIUM => 4096,
            self::THINKING_HIGH => 10240,
            default => 4096,
        };
    }

    /**
     * Extract text content from response
     */
    public function extractContent(array $response): string
    {
        return $response['choices'][0]['message']['content']
            ?? $response['content']
            ?? $response['text']
            ?? '';
    }

    /**
     * Extract thinking from response (if available)
     */
    public function extractThinking(array $response): ?string
    {
        return $response['choices'][0]['message']['thinking']
            ?? $response['thinking']
            ?? null;
    }
}
