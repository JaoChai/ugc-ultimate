<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class R2StorageException extends RuntimeException
{
    public const CODE_UPLOAD_FAILED = 1001;
    public const CODE_DOWNLOAD_FAILED = 1002;
    public const CODE_FILE_TOO_LARGE = 1003;
    public const CODE_INVALID_CONTENT = 1004;
    public const CODE_CONNECTION_FAILED = 1005;
    public const CODE_TIMEOUT = 1006;

    protected array $context = [];

    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function uploadFailed(string $path, Throwable $e): self
    {
        return new self(
            "Failed to upload file to R2: {$path}",
            self::CODE_UPLOAD_FAILED,
            $e,
            ['path' => $path, 'error' => $e->getMessage()]
        );
    }

    public static function downloadFailed(string $url, int $statusCode): self
    {
        return new self(
            "Failed to download file from URL (HTTP {$statusCode})",
            self::CODE_DOWNLOAD_FAILED,
            null,
            ['url' => $url, 'status_code' => $statusCode]
        );
    }

    public static function fileTooLarge(int $size, int $maxSize): self
    {
        $sizeMb = round($size / 1024 / 1024, 2);
        $maxMb = round($maxSize / 1024 / 1024, 2);

        return new self(
            "File size ({$sizeMb}MB) exceeds maximum allowed ({$maxMb}MB)",
            self::CODE_FILE_TOO_LARGE,
            null,
            ['size' => $size, 'max_size' => $maxSize]
        );
    }

    public static function connectionFailed(string $message, ?Throwable $e = null): self
    {
        return new self(
            "R2 connection failed: {$message}",
            self::CODE_CONNECTION_FAILED,
            $e,
            ['error' => $message]
        );
    }

    public static function timeout(string $operation, int $timeoutSeconds): self
    {
        return new self(
            "R2 operation timed out: {$operation} (timeout: {$timeoutSeconds}s)",
            self::CODE_TIMEOUT,
            null,
            ['operation' => $operation, 'timeout' => $timeoutSeconds]
        );
    }

    /**
     * Check if this error is retryable
     */
    public function isRetryable(): bool
    {
        return in_array($this->code, [
            self::CODE_CONNECTION_FAILED,
            self::CODE_TIMEOUT,
        ], true);
    }
}
