<?php

namespace App\Services;

use App\Exceptions\R2StorageException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class FFMpegService
{
    protected string $ffmpegPath;
    protected string $ffprobePath;
    protected string $tempDir;
    protected R2StorageService $r2Storage;

    public function __construct(R2StorageService $r2Storage)
    {
        $this->ffmpegPath = config('services.ffmpeg.path', '/usr/bin/ffmpeg');
        $this->ffprobePath = config('services.ffmpeg.ffprobe_path', '/usr/bin/ffprobe');
        $this->tempDir = storage_path('app/temp');
        $this->r2Storage = $r2Storage;

        // Ensure temp directory exists
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Compose video from images and audio
     */
    public function composeVideo(
        array $images,
        ?string $audioUrl,
        array $settings = []
    ): array {
        $jobId = Str::uuid()->toString();
        $outputPath = "{$this->tempDir}/{$jobId}_output.mp4";

        try {
            // Download all assets
            $localImages = $this->downloadImages($images, $jobId);
            $localAudio = $audioUrl ? $this->downloadAudio($audioUrl, $jobId) : null;

            // Get audio duration if available
            $audioDuration = $localAudio ? $this->getMediaDuration($localAudio) : null;

            // Build and execute FFmpeg command
            $command = $this->buildComposeCommand(
                $localImages,
                $localAudio,
                $outputPath,
                $audioDuration,
                $settings
            );

            $this->executeCommand($command);

            // Upload to R2
            $r2Url = $this->uploadToR2($outputPath, $jobId);

            // Cleanup
            $this->cleanup($jobId);

            return [
                'success' => true,
                'video_url' => $r2Url,
                'duration' => $this->getMediaDuration($outputPath),
                'job_id' => $jobId,
            ];

        } catch (\Exception $e) {
            $this->cleanup($jobId);
            throw $e;
        }
    }

    /**
     * Download images to local temp directory
     */
    protected function downloadImages(array $images, string $jobId): array
    {
        $localPaths = [];

        foreach ($images as $index => $image) {
            $url = $image['url'] ?? $image['image_url'] ?? '';
            if (empty($url)) continue;

            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
            $localPath = "{$this->tempDir}/{$jobId}_image_{$index}.{$extension}";

            $response = Http::timeout(60)->get($url);
            if ($response->successful()) {
                file_put_contents($localPath, $response->body());
                $localPaths[] = [
                    'path' => $localPath,
                    'duration' => $image['duration'] ?? 5,
                    'transition_in' => $image['transition_in'] ?? 'fade',
                    'transition_out' => $image['transition_out'] ?? 'fade',
                    'ken_burns' => $image['ken_burns'] ?? ['zoom' => 1.05, 'direction' => 'up'],
                ];
            }
        }

        return $localPaths;
    }

    /**
     * Download audio to local temp directory
     */
    protected function downloadAudio(string $url, string $jobId): string
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp3';
        $localPath = "{$this->tempDir}/{$jobId}_audio.{$extension}";

        $response = Http::timeout(120)->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download audio');
        }

        file_put_contents($localPath, $response->body());
        return $localPath;
    }

    /**
     * Build FFmpeg compose command
     */
    protected function buildComposeCommand(
        array $images,
        ?string $audioPath,
        string $outputPath,
        ?float $audioDuration,
        array $settings
    ): array {
        $width = $settings['width'] ?? 1920;
        $height = $settings['height'] ?? 1080;
        $fps = $settings['fps'] ?? 30;
        $transitionDuration = $settings['transition_duration'] ?? 0.5;

        // Calculate total duration
        $totalDuration = array_sum(array_column($images, 'duration'));
        if ($audioDuration && $audioDuration > $totalDuration) {
            // Extend last image to match audio
            $images[count($images) - 1]['duration'] += ($audioDuration - $totalDuration);
        }

        // Build filter complex for Ken Burns and transitions
        $filterComplex = $this->buildFilterComplex($images, $width, $height, $fps, $transitionDuration);

        $command = [$this->ffmpegPath, '-y'];

        // Add image inputs
        foreach ($images as $image) {
            $command = array_merge($command, [
                '-loop', '1',
                '-t', (string)$image['duration'],
                '-i', $image['path'],
            ]);
        }

        // Add audio input if available
        if ($audioPath) {
            $command = array_merge($command, ['-i', $audioPath]);
        }

        // Add filter complex
        $command = array_merge($command, [
            '-filter_complex', $filterComplex,
            '-map', '[outv]',
        ]);

        // Map audio if available
        if ($audioPath) {
            $audioIndex = count($images);
            $command = array_merge($command, ['-map', "{$audioIndex}:a"]);
        }

        // Output settings
        $command = array_merge($command, [
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '23',
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '192k',
            '-shortest',
            $outputPath,
        ]);

        return $command;
    }

    /**
     * Build FFmpeg filter complex for Ken Burns effect and crossfade
     */
    protected function buildFilterComplex(
        array $images,
        int $width,
        int $height,
        int $fps,
        float $transitionDuration
    ): string {
        $filters = [];
        $count = count($images);

        // Process each image with Ken Burns effect
        foreach ($images as $i => $image) {
            $duration = $image['duration'];
            $kenBurns = $image['ken_burns'];
            $zoom = $kenBurns['zoom'] ?? 1.05;
            $direction = $kenBurns['direction'] ?? 'up';

            // Calculate zoom parameters
            $frames = (int)($duration * $fps);
            $zoomStep = ($zoom - 1) / $frames;

            // Ken Burns zoompan filter
            $x = match($direction) {
                'left' => "iw/2-(iw/zoom/2)+'on/{$frames}*(iw-iw/zoom)'",
                'right' => "iw/2-(iw/zoom/2)-'on/{$frames}*(iw-iw/zoom)'",
                default => "iw/2-(iw/zoom/2)",
            };

            $y = match($direction) {
                'up' => "ih/2-(ih/zoom/2)+'on/{$frames}*(ih-ih/zoom)'",
                'down' => "ih/2-(ih/zoom/2)-'on/{$frames}*(ih-ih/zoom)'",
                default => "ih/2-(ih/zoom/2)",
            };

            $filters[] = "[{$i}:v]scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,zoompan=z='min(zoom+{$zoomStep},1.5)':x='{$x}':y='{$y}':d={$frames}:s={$width}x{$height}:fps={$fps},setsar=1[v{$i}]";
        }

        // Apply crossfade transitions between clips
        if ($count > 1) {
            $prevLabel = 'v0';
            for ($i = 1; $i < $count; $i++) {
                $offset = array_sum(array_slice(array_column($images, 'duration'), 0, $i)) - $transitionDuration;
                $outputLabel = $i === $count - 1 ? 'outv' : "cf{$i}";
                $filters[] = "[{$prevLabel}][v{$i}]xfade=transition=fade:duration={$transitionDuration}:offset={$offset}[{$outputLabel}]";
                $prevLabel = $outputLabel;
            }
        } else {
            $filters[] = "[v0]copy[outv]";
        }

        return implode(';', $filters);
    }

    /**
     * Add Ken Burns effect to a single image
     */
    public function addKenBurnsEffect(
        string $imagePath,
        float $duration,
        string $outputPath,
        array $kenBurns = []
    ): string {
        $zoom = $kenBurns['zoom'] ?? 1.05;
        $direction = $kenBurns['direction'] ?? 'up';
        $width = 1920;
        $height = 1080;
        $fps = 30;
        $frames = (int)($duration * $fps);

        $zoomStep = ($zoom - 1) / $frames;

        $x = match($direction) {
            'left' => "iw/2-(iw/zoom/2)+'on/{$frames}*(iw-iw/zoom)'",
            'right' => "iw/2-(iw/zoom/2)-'on/{$frames}*(iw-iw/zoom)'",
            default => "iw/2-(iw/zoom/2)",
        };

        $y = match($direction) {
            'up' => "ih/2-(ih/zoom/2)+'on/{$frames}*(ih-ih/zoom)'",
            'down' => "ih/2-(ih/zoom/2)-'on/{$frames}*(ih-ih/zoom)'",
            default => "ih/2-(ih/zoom/2)",
        };

        $command = [
            $this->ffmpegPath, '-y',
            '-loop', '1',
            '-i', $imagePath,
            '-vf', "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,zoompan=z='min(zoom+{$zoomStep},1.5)':x='{$x}':y='{$y}':d={$frames}:s={$width}x{$height}:fps={$fps}",
            '-t', (string)$duration,
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            $outputPath,
        ];

        $this->executeCommand($command);
        return $outputPath;
    }

    /**
     * Get media duration
     */
    public function getMediaDuration(string $path): float
    {
        $command = [
            $this->ffprobePath,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $path,
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return 0;
        }

        return (float)trim($process->getOutput());
    }

    /**
     * Execute FFmpeg command
     */
    protected function executeCommand(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(600); // 10 minutes timeout

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Upload video to R2 storage with proper error handling
     */
    protected function uploadToR2(string $localPath, string $jobId): string
    {
        $r2Path = "videos/{$jobId}.mp4";

        Log::info('FFMpegService: Uploading video to R2', [
            'local_path' => $localPath,
            'r2_path' => $r2Path,
            'file_size' => filesize($localPath),
        ]);

        try {
            // Use stream for memory efficiency with large video files
            $stream = fopen($localPath, 'rb');

            if ($stream === false) {
                throw new \RuntimeException("Cannot open file for reading: {$localPath}");
            }

            try {
                // Read file content from stream
                $content = stream_get_contents($stream);

                if ($content === false) {
                    throw new \RuntimeException("Failed to read file content: {$localPath}");
                }

                $url = $this->r2Storage->upload($content, $r2Path, 'video/mp4');

                Log::info('FFMpegService: Video uploaded to R2 successfully', [
                    'r2_path' => $r2Path,
                    'url' => $url,
                ]);

                return $url;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        } catch (R2StorageException $e) {
            Log::error('FFMpegService: R2 upload failed', [
                'r2_path' => $r2Path,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);

            throw $e; // Re-throw to let caller handle
        } catch (\Exception $e) {
            Log::error('FFMpegService: Unexpected error during R2 upload', [
                'r2_path' => $r2Path,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            throw new R2StorageException(
                "Failed to upload video to R2: {$e->getMessage()}",
                R2StorageException::CODE_UPLOAD_FAILED,
                $e,
                ['r2_path' => $r2Path, 'local_path' => $localPath]
            );
        }
    }

    /**
     * Cleanup temp files
     */
    protected function cleanup(string $jobId): void
    {
        $pattern = "{$this->tempDir}/{$jobId}_*";
        foreach (glob($pattern) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Get video info
     */
    public function getVideoInfo(string $path): array
    {
        $command = [
            $this->ffprobePath,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $path,
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return json_decode($process->getOutput(), true);
    }

    /**
     * Extract thumbnail from video
     */
    public function extractThumbnail(string $videoPath, string $outputPath, float $timestamp = 0): string
    {
        $command = [
            $this->ffmpegPath, '-y',
            '-ss', (string)$timestamp,
            '-i', $videoPath,
            '-vframes', '1',
            '-vf', 'scale=640:-1',
            $outputPath,
        ];

        $this->executeCommand($command);
        return $outputPath;
    }
}
