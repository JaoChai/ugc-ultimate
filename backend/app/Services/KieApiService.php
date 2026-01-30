<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KieApiService
{
    protected const BASE_URL = 'https://api.kie.ai';
    protected const UPLOAD_URL = 'https://kieai.redpandaai.co';

    protected ?string $apiKey = null;
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    public function setApiKey(string $encryptedKey): self
    {
        $this->apiKey = $this->encryption->decrypt($encryptedKey);
        return $this;
    }

    public function setApiKeyFromModel(ApiKey $apiKey): self
    {
        $this->apiKey = $this->encryption->decrypt($apiKey->key_encrypted);

        // Update last used timestamp
        $apiKey->update(['last_used_at' => now()]);

        return $this;
    }

    protected function client(): PendingRequest
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('API key not set');
        }

        return Http::baseUrl(self::BASE_URL)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(120);
    }

    protected function uploadClient(): PendingRequest
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('API key not set');
        }

        return Http::baseUrl(self::UPLOAD_URL)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->timeout(60);
    }

    /**
     * Check remaining credits
     */
    public function getCredits(): array
    {
        $response = $this->client()->get('/api/v1/chat/credit');

        return $this->handleResponse($response);
    }

    /**
     * Get download URL for a file (valid for 20 minutes)
     */
    public function getDownloadUrl(string $fileUrl): array
    {
        $response = $this->client()->post('/api/v1/common/download-url', [
            'url' => $fileUrl,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Upload file from URL
     */
    public function uploadFromUrl(string $url): array
    {
        $response = $this->uploadClient()->post('/api/file-url-upload', [
            'url' => $url,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Upload file as base64 (for files < 10MB)
     */
    public function uploadBase64(string $base64Data, string $filename): array
    {
        $response = $this->uploadClient()->post('/api/file-base64-upload', [
            'file' => $base64Data,
            'filename' => $filename,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Upload file via stream (for large files)
     */
    public function uploadStream(string $filePath): array
    {
        $response = $this->uploadClient()
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post('/api/file-stream-upload');

        return $this->handleResponse($response);
    }

    /**
     * Generic POST request to kie.ai API
     */
    public function post(string $endpoint, array $data = []): array
    {
        $response = $this->client()->post($endpoint, $data);

        return $this->handleResponse($response);
    }

    /**
     * Generic GET request to kie.ai API
     */
    public function get(string $endpoint, array $query = []): array
    {
        $response = $this->client()->get($endpoint, $query);

        return $this->handleResponse($response);
    }

    /**
     * Handle API response
     */
    protected function handleResponse(Response $response): array
    {
        $data = $response->json();

        if (!$response->successful()) {
            Log::error('kie.ai API Error', [
                'status' => $response->status(),
                'body' => $data,
            ]);

            $message = match ($response->status()) {
                401 => 'Invalid API key',
                402 => 'Insufficient credits',
                422 => 'Invalid request data',
                429 => 'Rate limit exceeded',
                500 => 'kie.ai server error',
                default => $data['msg'] ?? 'Unknown error',
            };

            throw new \RuntimeException($message, $response->status());
        }

        if (isset($data['code']) && $data['code'] !== 200) {
            throw new \RuntimeException($data['msg'] ?? 'API error', $data['code']);
        }

        return $data['data'] ?? $data;
    }

    /**
     * Poll for task completion
     */
    public function pollTaskStatus(
        string $endpoint,
        string $taskId,
        int $maxAttempts = 60,
        int $intervalSeconds = 5
    ): array {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $result = $this->get($endpoint, ['task_id' => $taskId]);

            $status = $result['status'] ?? $result['state'] ?? null;

            if (in_array($status, ['completed', 'success', 'done'])) {
                return $result;
            }

            if (in_array($status, ['failed', 'error'])) {
                throw new \RuntimeException($result['error'] ?? 'Task failed');
            }

            sleep($intervalSeconds);
            $attempts++;
        }

        throw new \RuntimeException('Task timeout after ' . ($maxAttempts * $intervalSeconds) . ' seconds');
    }
}
