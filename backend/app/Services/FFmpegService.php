<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class FFmpegService
{
    protected string $ffmpegPath;
    protected string $ffprobePath;
    protected string $tempDir;

    public function __construct()
    {
        $this->ffmpegPath = config('services.ffmpeg.path', 'ffmpeg');
        $this->ffprobePath = config('services.ffmpeg.ffprobe_path', 'ffprobe');
        $this->tempDir = storage_path('app/temp');

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Combine video clips with audio
     *
     * @param array $videoClips Array of video file paths
     * @param string $audioPath Path to audio file
     * @param string $outputPath Output file path
     * @param array $options Additional options
     */
    public function combineVideoWithAudio(
        array $videoClips,
        string $audioPath,
        string $outputPath,
        array $options = []
    ): bool {
        // Create concat file for video clips
        $concatFile = $this->createConcatFile($videoClips);

        $resolution = $options['resolution'] ?? '1920x1080';
        $fps = $options['fps'] ?? 30;
        $videoBitrate = $options['video_bitrate'] ?? '5M';
        $audioBitrate = $options['audio_bitrate'] ?? '192k';

        // Build FFmpeg command
        $command = [
            $this->ffmpegPath,
            '-y', // Overwrite output
            '-f', 'concat',
            '-safe', '0',
            '-i', $concatFile, // Video input
            '-i', $audioPath,  // Audio input
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '23',
            '-b:v', $videoBitrate,
            '-r', (string) $fps,
            '-s', $resolution,
            '-c:a', 'aac',
            '-b:a', $audioBitrate,
            '-map', '0:v:0', // Use video from first input
            '-map', '1:a:0', // Use audio from second input
            '-shortest',     // End when shortest stream ends
            '-movflags', '+faststart',
            $outputPath,
        ];

        $result = $this->runCommand($command);

        // Cleanup temp concat file
        @unlink($concatFile);

        return $result;
    }

    /**
     * Add text overlay to video (for lyrics, captions)
     *
     * @param string $inputPath Input video path
     * @param string $outputPath Output video path
     * @param array $textOverlays Array of text overlays with timing
     */
    public function addTextOverlay(
        string $inputPath,
        string $outputPath,
        array $textOverlays,
        array $options = []
    ): bool {
        $fontPath = $options['font'] ?? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $fontSize = $options['font_size'] ?? 48;
        $fontColor = $options['font_color'] ?? 'white';
        $borderColor = $options['border_color'] ?? 'black';
        $borderWidth = $options['border_width'] ?? 2;

        // Build drawtext filter
        $filters = [];
        foreach ($textOverlays as $overlay) {
            $text = $this->escapeText($overlay['text']);
            $startTime = $overlay['start'] ?? 0;
            $endTime = $overlay['end'] ?? $startTime + 3;
            $position = $overlay['position'] ?? 'center';
            $x = $overlay['x'] ?? $this->getXPosition($position);
            $y = $overlay['y'] ?? $this->getYPosition($position);

            $filters[] = sprintf(
                "drawtext=text='%s':fontfile=%s:fontsize=%d:fontcolor=%s:borderw=%d:bordercolor=%s:x=%s:y=%s:enable='between(t,%s,%s)'",
                $text,
                $fontPath,
                $fontSize,
                $fontColor,
                $borderWidth,
                $borderColor,
                $x,
                $y,
                $startTime,
                $endTime
            );
        }

        $filterString = implode(',', $filters);

        $command = [
            $this->ffmpegPath,
            '-y',
            '-i', $inputPath,
            '-vf', $filterString,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '23',
            '-c:a', 'copy',
            '-movflags', '+faststart',
            $outputPath,
        ];

        return $this->runCommand($command);
    }

    /**
     * Add animated lyrics overlay with timing
     */
    public function addLyricsOverlay(
        string $inputPath,
        string $outputPath,
        string $lyrics,
        array $timings,
        array $options = []
    ): bool {
        $textOverlays = $this->parseLyricsToOverlays($lyrics, $timings, $options);
        return $this->addTextOverlay($inputPath, $outputPath, $textOverlays, $options);
    }

    /**
     * Concatenate video clips
     */
    public function concatenateVideos(
        array $videoPaths,
        string $outputPath,
        array $options = []
    ): bool {
        $concatFile = $this->createConcatFile($videoPaths);

        $command = [
            $this->ffmpegPath,
            '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $concatFile,
            '-c', 'copy',
            '-movflags', '+faststart',
            $outputPath,
        ];

        // If transcoding is needed
        if ($options['transcode'] ?? false) {
            $command = [
                $this->ffmpegPath,
                '-y',
                '-f', 'concat',
                '-safe', '0',
                '-i', $concatFile,
                '-c:v', 'libx264',
                '-preset', 'medium',
                '-crf', '23',
                '-c:a', 'aac',
                '-b:a', '192k',
                '-movflags', '+faststart',
                $outputPath,
            ];
        }

        $result = $this->runCommand($command);
        @unlink($concatFile);

        return $result;
    }

    /**
     * Add transition between video clips
     */
    public function addTransitions(
        array $videoPaths,
        string $outputPath,
        string $transition = 'fade',
        float $duration = 0.5
    ): bool {
        if (count($videoPaths) < 2) {
            return $this->concatenateVideos($videoPaths, $outputPath);
        }

        // Build complex filter for transitions
        $inputs = '';
        $filterComplex = '';
        $lastOutput = '';

        foreach ($videoPaths as $i => $path) {
            $inputs .= "-i {$path} ";
        }

        // Create filter for each transition
        for ($i = 0; $i < count($videoPaths) - 1; $i++) {
            $input1 = $i === 0 ? "[0:v]" : $lastOutput;
            $input2 = "[" . ($i + 1) . ":v]";
            $outputLabel = "[v" . ($i + 1) . "]";
            $lastOutput = $outputLabel;

            $offset = $this->getVideoDuration($videoPaths[$i]) - $duration;

            $filterComplex .= "{$input1}{$input2}xfade=transition={$transition}:duration={$duration}:offset={$offset}{$outputLabel};";
        }

        // Audio crossfade
        $audioFilter = '';
        for ($i = 0; $i < count($videoPaths) - 1; $i++) {
            $input1 = $i === 0 ? "[0:a]" : "[a" . $i . "]";
            $input2 = "[" . ($i + 1) . ":a]";
            $outputLabel = "[a" . ($i + 1) . "]";

            $offset = $this->getVideoDuration($videoPaths[$i]) - $duration;
            $audioFilter .= "{$input1}{$input2}acrossfade=d={$duration}:c1=tri:c2=tri{$outputLabel};";
        }

        $filterComplex = trim($filterComplex . $audioFilter, ';');

        $command = $inputs . " -filter_complex \"{$filterComplex}\" -map \"{$lastOutput}\" -map \"[a" . (count($videoPaths) - 1) . "]\" -c:v libx264 -preset medium -crf 23 -c:a aac -b:a 192k -movflags +faststart {$outputPath}";

        return $this->runCommand([$this->ffmpegPath, '-y'] + explode(' ', trim($command)));
    }

    /**
     * Get video duration
     */
    public function getVideoDuration(string $path): float
    {
        $command = [
            $this->ffprobePath,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $path,
        ];

        $result = Process::run(implode(' ', $command));

        return (float) trim($result->output());
    }

    /**
     * Get video info (resolution, fps, codec)
     */
    public function getVideoInfo(string $path): array
    {
        $command = [
            $this->ffprobePath,
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height,r_frame_rate,codec_name',
            '-of', 'json',
            $path,
        ];

        $result = Process::run(implode(' ', $command));
        $data = json_decode($result->output(), true);

        $stream = $data['streams'][0] ?? [];

        // Parse frame rate
        $fps = 30;
        if (!empty($stream['r_frame_rate'])) {
            $parts = explode('/', $stream['r_frame_rate']);
            $fps = count($parts) === 2 ? (int) $parts[0] / (int) $parts[1] : (int) $parts[0];
        }

        return [
            'width' => $stream['width'] ?? 0,
            'height' => $stream['height'] ?? 0,
            'fps' => $fps,
            'codec' => $stream['codec_name'] ?? 'unknown',
            'duration' => $this->getVideoDuration($path),
        ];
    }

    /**
     * Resize video
     */
    public function resize(
        string $inputPath,
        string $outputPath,
        int $width,
        int $height
    ): bool {
        $command = [
            $this->ffmpegPath,
            '-y',
            '-i', $inputPath,
            '-vf', "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2",
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '23',
            '-c:a', 'copy',
            '-movflags', '+faststart',
            $outputPath,
        ];

        return $this->runCommand($command);
    }

    /**
     * Extract thumbnail from video
     */
    public function extractThumbnail(
        string $inputPath,
        string $outputPath,
        float $timestamp = 1.0
    ): bool {
        $command = [
            $this->ffmpegPath,
            '-y',
            '-i', $inputPath,
            '-ss', (string) $timestamp,
            '-vframes', '1',
            '-q:v', '2',
            $outputPath,
        ];

        return $this->runCommand($command);
    }

    /**
     * Create concat file for FFmpeg
     */
    protected function createConcatFile(array $files): string
    {
        $concatFile = $this->tempDir . '/' . Str::uuid() . '.txt';
        $content = '';

        foreach ($files as $file) {
            $content .= "file '" . addslashes($file) . "'\n";
        }

        file_put_contents($concatFile, $content);

        return $concatFile;
    }

    /**
     * Run FFmpeg command
     */
    protected function runCommand(array $command): bool
    {
        $commandString = implode(' ', array_map('escapeshellarg', $command));

        Log::info('Running FFmpeg command', ['command' => $commandString]);

        $result = Process::timeout(600)->run($commandString);

        if (!$result->successful()) {
            Log::error('FFmpeg command failed', [
                'command' => $commandString,
                'error' => $result->errorOutput(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Escape text for FFmpeg drawtext filter
     */
    protected function escapeText(string $text): string
    {
        // Escape special characters for FFmpeg
        $text = str_replace(['\\', "'", ':', '%'], ['\\\\', "\\'", '\\:', '\\%'], $text);
        return $text;
    }

    /**
     * Get X position based on position name
     */
    protected function getXPosition(string $position): string
    {
        return match ($position) {
            'left' => '50',
            'right' => 'w-tw-50',
            'center' => '(w-tw)/2',
            default => '(w-tw)/2',
        };
    }

    /**
     * Get Y position based on position name
     */
    protected function getYPosition(string $position): string
    {
        return match ($position) {
            'top' => '50',
            'bottom' => 'h-th-100',
            'center' => '(h-th)/2',
            default => 'h-th-100', // Default to bottom
        };
    }

    /**
     * Parse lyrics string to text overlays with timing
     */
    protected function parseLyricsToOverlays(string $lyrics, array $timings, array $options = []): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $lyrics)));
        $overlays = [];

        foreach ($lines as $i => $line) {
            // Skip section markers like [Verse 1], [Chorus], etc.
            if (preg_match('/^\[.*\]$/', $line)) {
                continue;
            }

            $timing = $timings[$i] ?? null;
            if (!$timing) {
                continue;
            }

            $overlays[] = [
                'text' => $line,
                'start' => $timing['start'] ?? 0,
                'end' => $timing['end'] ?? ($timing['start'] ?? 0) + 3,
                'position' => $options['lyrics_position'] ?? 'bottom',
            ];
        }

        return $overlays;
    }

    /**
     * Download file from URL to temp directory
     */
    public function downloadToTemp(string $url): string
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4';
        $tempPath = $this->tempDir . '/' . Str::uuid() . '.' . $extension;

        $contents = file_get_contents($url);
        if ($contents === false) {
            throw new \RuntimeException('Failed to download file from URL');
        }

        file_put_contents($tempPath, $contents);

        return $tempPath;
    }

    /**
     * Cleanup temp files
     */
    public function cleanupTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file) && strpos($file, $this->tempDir) === 0) {
                @unlink($file);
            }
        }
    }
}
