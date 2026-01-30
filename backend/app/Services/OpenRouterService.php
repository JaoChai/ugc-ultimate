<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class OpenRouterService
{
    protected string $baseUrl = 'https://openrouter.ai/api/v1';
    protected ?string $apiKey = null;
    protected EncryptionService $encryption;

    // Default model
    public const DEFAULT_MODEL = 'google/gemini-2.0-flash-exp';

    // Default parameters
    public const DEFAULT_TEMPERATURE = 0.7;
    public const DEFAULT_MAX_TOKENS = 2000;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Set API key from ApiKey model
     */
    public function setApiKey(ApiKey $apiKey): self
    {
        $decrypted = $this->encryption->decrypt($apiKey->key_encrypted);
        if (!$decrypted) {
            throw new \RuntimeException('Failed to decrypt API key');
        }
        $this->apiKey = $decrypted;
        return $this;
    }

    /**
     * Set API key directly
     */
    public function setApiKeyString(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Chat completion
     */
    public function chat(
        string $systemPrompt,
        string $userPrompt,
        string $model = self::DEFAULT_MODEL,
        float $temperature = self::DEFAULT_TEMPERATURE,
        int $maxTokens = self::DEFAULT_MAX_TOKENS
    ): array {
        $this->ensureApiKey();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        return $this->request('/chat/completions', $payload);
    }

    /**
     * Chat with message history
     */
    public function chatWithHistory(
        array $messages,
        string $model = self::DEFAULT_MODEL,
        float $temperature = self::DEFAULT_TEMPERATURE,
        int $maxTokens = self::DEFAULT_MAX_TOKENS
    ): array {
        $this->ensureApiKey();

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        return $this->request('/chat/completions', $payload);
    }

    /**
     * Generate JSON response with schema
     */
    public function generateJson(
        string $systemPrompt,
        string $userPrompt,
        array $schema,
        string $model = self::DEFAULT_MODEL,
        float $temperature = self::DEFAULT_TEMPERATURE
    ): array {
        $this->ensureApiKey();

        // Add JSON instruction to system prompt
        $jsonSystemPrompt = $systemPrompt . "\n\nIMPORTANT: You must respond with valid JSON only. No markdown, no explanation, just the JSON object.";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $jsonSystemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $temperature,
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->request('/chat/completions', $payload);
        $content = $this->extractContent($response);

        return $this->parseJson($content);
    }

    /**
     * Simple text generation
     */
    public function generate(
        string $prompt,
        string $model = self::DEFAULT_MODEL,
        float $temperature = self::DEFAULT_TEMPERATURE,
        int $maxTokens = self::DEFAULT_MAX_TOKENS
    ): array {
        $this->ensureApiKey();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        return $this->request('/chat/completions', $payload);
    }

    /**
     * Streaming chat (returns generator)
     */
    public function streamChat(
        string $systemPrompt,
        string $userPrompt,
        string $model = self::DEFAULT_MODEL,
        float $temperature = self::DEFAULT_TEMPERATURE
    ): \Generator {
        $this->ensureApiKey();

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $temperature,
            'stream' => true,
        ];

        $response = Http::withHeaders($this->getHeaders())
            ->timeout(120)
            ->withOptions(['stream' => true])
            ->post($this->baseUrl . '/chat/completions', $payload);

        $body = $response->getBody();

        while (!$body->eof()) {
            $line = $body->read(4096);
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    break;
                }
                $json = json_decode($data, true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    yield $json['choices'][0]['delta']['content'];
                }
            }
        }
    }

    /**
     * Make HTTP request to OpenRouter
     */
    protected function request(string $endpoint, array $payload): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(120)
                ->post($this->baseUrl . $endpoint, $payload);

            if (!$response->successful()) {
                $error = $response->json();
                throw new \RuntimeException(
                    'OpenRouter API error: ' . ($error['error']['message'] ?? $response->body()),
                    $response->status()
                );
            }

            return $response->json();
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenRouter request failed: ' . $e->getMessage());
        }
    }

    /**
     * Get request headers
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ];
    }

    /**
     * Ensure API key is set
     */
    protected function ensureApiKey(): void
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('OpenRouter API key not set');
        }
    }

    /**
     * Extract content from response
     */
    public function extractContent(array $response): string
    {
        return $response['choices'][0]['message']['content']
            ?? $response['content']
            ?? '';
    }

    /**
     * Parse JSON from content
     */
    protected function parseJson(string $content): array
    {
        // Try direct parse
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Try to find JSON object in content
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        throw new \RuntimeException('Failed to parse JSON response: ' . $content);
    }

    /**
     * Get available models (static list - can be fetched from API if needed)
     */
    public static function getPopularModels(): array
    {
        return [
            'google/gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental)',
            'google/gemini-2.0-pro-exp' => 'Gemini 2.0 Pro (Experimental)',
            'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet',
            'anthropic/claude-3-opus' => 'Claude 3 Opus',
            'openai/gpt-4o' => 'GPT-4o',
            'openai/gpt-4o-mini' => 'GPT-4o Mini',
            'meta-llama/llama-3.1-70b-instruct' => 'Llama 3.1 70B',
        ];
    }
}
