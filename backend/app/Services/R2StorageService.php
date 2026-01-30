<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class R2StorageService
{
    protected ?S3Client $client = null;
    protected string $bucket;
    protected string $publicUrl;

    public function __construct()
    {
        $this->bucket = config('services.r2.bucket', 'ugc-ultimate');
        $this->publicUrl = config('services.r2.public_url', '');
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
     * Upload a file to R2
     */
    public function upload(
        string|UploadedFile $file,
        string $path,
        ?string $contentType = null
    ): string {
        $this->ensureClient();

        if ($file instanceof UploadedFile) {
            $contents = file_get_contents($file->getRealPath());
            $contentType = $contentType ?? $file->getMimeType();
        } else {
            $contents = $file;
        }

        $key = ltrim($path, '/');

        $params = [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $contents,
        ];

        if ($contentType) {
            $params['ContentType'] = $contentType;
        }

        $this->client->putObject($params);

        return $this->getPublicUrl($key);
    }

    /**
     * Upload from URL (download and upload to R2)
     */
    public function uploadFromUrl(string $url, string $path): string
    {
        $response = Http::timeout(60)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download file from URL');
        }

        $contentType = $response->header('Content-Type');

        return $this->upload($response->body(), $path, $contentType);
    }

    /**
     * Upload from base64
     */
    public function uploadBase64(string $base64Data, string $path, string $contentType): string
    {
        $contents = base64_decode($base64Data);

        return $this->upload($contents, $path, $contentType);
    }

    /**
     * Delete a file from R2
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

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if file exists
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
        } catch (\Exception $e) {
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

        $command = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        $request = $this->client->createPresignedRequest(
            $command,
            "+{$expiresInMinutes} minutes"
        );

        return (string) $request->getUri();
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
                'url' => $this->getPublicUrl($object['Key']),
            ];
        }

        return $files;
    }

    /**
     * Get total storage used
     */
    public function getTotalSize(string $prefix = ''): int
    {
        $files = $this->list($prefix);

        return array_sum(array_column($files, 'size'));
    }
}
