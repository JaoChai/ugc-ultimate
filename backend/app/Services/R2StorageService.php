<?php

namespace App\Services;

use App\Exceptions\R2StorageException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class R2StorageService
{
    protected ?S3Client $client = null;
    protected string $bucket;
    protected string $publicUrl;

    // Configuration
    protected int $maxFileSize;
    protected int $uploadTimeout;
    protected int $downloadTimeout;
    protected int $retryAttempts;
    protected int $retryDelay;

    public function __construct()
    {
        $this->bucket = config('services.r2.bucket', 'ugc-ultimate');
        $this->publicUrl = config('services.r2.public_url', '');

        // Load configuration with defaults
        $this->maxFileSize = (int) config('services.r2.max_file_size', 100 * 1024 * 1024);
        $this->uploadTimeout = (int) config('services.r2.upload_timeout', 120);
        $this->downloadTimeout = (int) config('services.r2.download_timeout', 180);
        $this->retryAttempts = (int) config('services.r2.retry_attempts', 3);
        $this->retryDelay = (int) config('services.r2.retry_delay', 2);
    }

    /**
     * Initialize S3 client with R2 credentials
     */
    public function setCredentials(
        string $accountId,
        string $accessKeyId,
        string $secretAccessKey
    ): self {
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => "https://{$accountId}.r2.cloudflarestorage.com",
            'credentials' => [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ],
        ]);

        return $this;
    }

    /**
     * Set credentials from environment
     */
    public function useEnvCredentials(): self
    {
        return $this->setCredentials(
            config('services.r2.account_id'),
            config('services.r2.access_key_id'),
            config('services.r2.secret_access_key')
        );
    }

    protected function ensureClient(): void
    {
        if (!$this->client) {
            $this->useEnvCredentials();
        }
    }

    /**
     * Upload a file to R2 with error handling and retry logic
     */
    public function upload(
        string|UploadedFile $file,
        string $path,
        ?string $contentType = null
    ): string {
        $this->ensureClient();

        $key = ltrim($path, '/');

        if ($file instanceof UploadedFile) {
            $size = $file->getSize();
            $this->validateFileSize($size);

            // Use stream for memory efficiency
            $stream = fopen($file->getRealPath(), 'rb');
            $contentType = $contentType ?? $file->getMimeType();

            try {
                $this->uploadWithRetry($key, $stream, $contentType);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        } else {
            // String content
            $size = strlen($file);
            $this->validateFileSize($size);

            $this->uploadWithRetry($key, $file, $contentType);
        }

        Log::info('R2 upload completed', ['key' => $key, 'size' => $size ?? 0]);

        return $this->getPublicUrl($key);
    }

    /**
     * Upload with retry logic and exponential backoff
     */
    protected function uploadWithRetry(
        string $key,
        mixed $body,
        ?string $contentType,
        int $attempt = 0
    ): void {
        $params = [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $body,
        ];

        if ($contentType) {
            $params['ContentType'] = $contentType;
        }

        try {
            $this->client->putObject($params);
        } catch (S3Exception $e) {
            $errorCode = $e->getAwsErrorCode();

            // Retry for transient errors
            if ($this->isRetryableError($errorCode) && $attempt < $this->retryAttempts) {
                $delay = $this->retryDelay * pow(2, $attempt); // Exponential backoff

                Log::warning('R2 upload failed, retrying', [
                    'key' => $key,
                    'attempt' => $attempt + 1,
                    'max_attempts' => $this->retryAttempts,
                    'error_code' => $errorCode,
                    'delay_seconds' => $delay,
                ]);

                sleep($delay);

                // Reset stream position if needed
                if (is_resource($body)) {
                    rewind($body);
                }

                $this->uploadWithRetry($key, $body, $contentType, $attempt + 1);
                return;
            }

            Log::error('R2 upload failed after retries', [
                'key' => $key,
                'error' => $e->getMessage(),
                'error_code' => $errorCode,
                'attempts' => $attempt + 1,
            ]);

            throw R2StorageException::uploadFailed($key, $e);
        } catch (\Exception $e) {
            Log::error('R2 upload unexpected error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            throw R2StorageException::uploadFailed($key, $e);
        }
    }

    /**
     * Check if an error code is retryable
     */
    protected function isRetryableError(?string $errorCode): bool
    {
        $retryableCodes = [
            'RequestTimeout',
            'InternalError',
            'SlowDown',
            'ServiceUnavailable',
            'RequestTimeTooSkewed',
        ];

        return in_array($errorCode, $retryableCodes, true);
    }

    /**
     * Validate file size against maximum allowed
     */
    protected function validateFileSize(int $size): void
    {
        if ($size > $this->maxFileSize) {
            throw R2StorageException::fileTooLarge($size, $this->maxFileSize);
        }
    }

    /**
     * Upload from URL (download and upload to R2) with proper error handling
     */
    public function uploadFromUrl(string $url, string $path): string
    {
        Log::info('R2 downloading from URL', ['url' => $url, 'path' => $path]);

        try {
            $response = Http::timeout($this->downloadTimeout)->get($url);
        } catch (\Exception $e) {
            Log::error('R2 download timeout or connection error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw R2StorageException::connectionFailed("Download failed: {$e->getMessage()}", $e);
        }

        if (!$response->successful()) {
            Log::error('R2 download failed', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            throw R2StorageException::downloadFailed($url, $response->status());
        }

        $contentType = $response->header('Content-Type');
        $contentLength = (int) $response->header('Content-Length', 0);

        // Validate size if Content-Length is available
        if ($contentLength > 0) {
            $this->validateFileSize($contentLength);
        }

        return $this->upload($response->body(), $path, $contentType);
    }

    /**
     * Upload from base64 with validation
     */
    public function uploadBase64(string $base64Data, string $path, string $contentType): string
    {
        $contents = base64_decode($base64Data, true);

        if ($contents === false) {
            throw new R2StorageException(
                'Invalid base64 data',
                R2StorageException::CODE_INVALID_CONTENT
            );
        }

        return $this->upload($contents, $path, $contentType);
    }

    /**
     * Delete a file from R2 with proper logging
     */
    public function delete(string $path): bool
    {
        $this->ensureClient();

        $key = ltrim($path, '/');

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            Log::debug('R2 file deleted', ['key' => $key]);

            return true;
        } catch (S3Exception $e) {
            Log::warning('R2 delete failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'error_code' => $e->getAwsErrorCode(),
            ]);

            return false;
        }
    }

    /**
     * Check if file exists with proper error distinction
     */
    public function exists(string $path): bool
    {
        $this->ensureClient();

        $key = ltrim($path, '/');

        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return true;
        } catch (S3Exception $e) {
            // 404 is expected for non-existent files
            if ($e->getStatusCode() === 404) {
                return false;
            }

            // Log actual errors (not just "not found")
            Log::warning('R2 exists check failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'error_code' => $e->getAwsErrorCode(),
            ]);

            return false;
        }
    }

    /**
     * Get presigned URL for temporary access
     */
    public function getPresignedUrl(string $path, int $expiresInMinutes = 60): string
    {
        $this->ensureClient();

        $key = ltrim($path, '/');

        try {
            $command = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            $request = $this->client->createPresignedRequest(
                $command,
                "+{$expiresInMinutes} minutes"
            );

            return (string) $request->getUri();
        } catch (\Exception $e) {
            Log::error('R2 presigned URL generation failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            throw R2StorageException::connectionFailed("Presigned URL failed: {$e->getMessage()}", $e);
        }
    }

    /**
     * Get public URL for a file
     */
    public function getPublicUrl(string $path): string
    {
        $key = ltrim($path, '/');

        if ($this->publicUrl) {
            return rtrim($this->publicUrl, '/') . '/' . $key;
        }

        // Fallback to presigned URL if no public URL configured
        return $this->getPresignedUrl($path);
    }

    /**
     * Generate a unique path for a project asset
     */
    public function generateAssetPath(
        int $projectId,
        string $type,
        string $extension
    ): string {
        $uuid = Str::uuid();
        $date = now()->format('Y/m/d');

        return "projects/{$projectId}/{$date}/{$type}/{$uuid}.{$extension}";
    }

    /**
     * List files in a directory
     */
    public function list(string $prefix = '', int $maxKeys = 1000): array
    {
        $this->ensureClient();

        try {
            $result = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => ltrim($prefix, '/'),
                'MaxKeys' => $maxKeys,
            ]);

            $files = [];
            foreach ($result['Contents'] ?? [] as $object) {
                $files[] = [
                    'key' => $object['Key'],
                    'size' => $object['Size'],
                    'last_modified' => $object['LastModified'],
                ];
            }

            return $files;
        } catch (S3Exception $e) {
            Log::error('R2 list failed', [
                'prefix' => $prefix,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get total storage used
     */
    public function getTotalSize(string $prefix = ''): int
    {
        $files = $this->list($prefix);

        return array_sum(array_column($files, 'size'));
    }

    /**
     * Get configuration for external use
     */
    public function getConfig(): array
    {
        return [
            'max_file_size' => $this->maxFileSize,
            'upload_timeout' => $this->uploadTimeout,
            'download_timeout' => $this->downloadTimeout,
            'retry_attempts' => $this->retryAttempts,
            'retry_delay' => $this->retryDelay,
        ];
    }
}
