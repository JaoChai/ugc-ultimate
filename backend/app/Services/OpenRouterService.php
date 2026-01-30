<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * Streaming chat (returns generator) with proper error handling
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

        // Check initial HTTP status for pre-stream errors
        if (!$response->successful()) {
            $error = $response->json();
            $status = $response->status();
            $message = match ($status) {
                401 => 'Invalid API key',
                402 => 'Insufficient credits',
                429 => 'Rate limit exceeded',
                503 => 'Service unavailable',
                default => $error['error']['message'] ?? 'Stream initialization failed',
            };
            throw new \RuntimeException($message, $status);
        }

        $body = $response->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(4096);
            $buffer .= $chunk;

            // Process complete lines from buffer
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);

                if ($data === '[DONE]') {
                    return;
                }

                $json = json_decode($data, true);
                if (!$json) {
                    continue;
                }

                // Check for mid-stream error
                if (isset($json['error'])) {
                    Log::error('OpenRouter streaming error', [
                        'error' => $json['error'],
                        'model' => $model,
                    ]);
                    throw new \RuntimeException(
                        'Streaming error: ' . ($json['error']['message'] ?? 'Unknown error'),
                        $json['error']['code'] ?? 500
                    );
                }

                // Check for error finish reason
                $finishReason = $json['choices'][0]['finish_reason'] ?? null;
                if ($finishReason === 'error') {
                    throw new \RuntimeException('OpenRouter generation error mid-stream');
                }

                // Yield content
                if (isset($json['choices'][0]['delta']['content'])) {
                    yield $json['choices'][0]['delta']['content'];
                }
            }
        }
    }

    /**
     * Make HTTP request to OpenRouter with retry support for rate limiting
     */
    protected function request(string $endpoint, array $payload, int $retries = 0): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(120)
                ->post($this->baseUrl . $endpoint, $payload);

            if (!$response->successful()) {
                $error = $response->json();
                $status = $response->status();

                // Auto-retry for rate limit (max 2 retries)
                if ($status === 429 && $retries < 2) {
                    $retryAfter = (int) $response->header('Retry-After', 5);
                    $waitTime = min($retryAfter, 10); // Cap at 10 seconds

                    Log::info('OpenRouter rate limited, retrying', [
                        'retry_after' => $waitTime,
                        'attempt' => $retries + 1,
                        'model' => $payload['model'] ?? 'unknown',
                    ]);

                    sleep($waitTime);
                    return $this->request($endpoint, $payload, $retries + 1);
                }

                // Map HTTP status codes to user-friendly messages
                $message = match ($status) {
                    400 => 'Bad request: ' . ($error['error']['message'] ?? 'Invalid parameters'),
                    401 => 'Invalid API key',
                    402 => 'Insufficient credits or payment required',
                    403 => 'Content moderation: request flagged',
                    408 => 'Request timeout',
                    429 => 'Rate limit exceeded (retries exhausted)',
                    502 => 'Model is currently unavailable',
                    503 => 'OpenRouter service temporarily unavailable',
                    default => $error['error']['message'] ?? 'Unknown error',
                };

                Log::warning('OpenRouter API Error', [
                    'status' => $status,
                    'message' => $message,
                    'model' => $payload['model'] ?? 'unknown',
                    'endpoint' => $endpoint,
                ]);

                throw new \RuntimeException($message, $status);
            }

            return $response->json();
        } catch (RequestException $e) {
            Log::error('OpenRouter request failed', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);
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
