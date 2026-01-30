<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\JobLog;
use App\Models\Project;
use App\Services\FFmpegService;
use App\Services\R2StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ComposeVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600; // 10 minutes for video processing

    public function __construct(
        public int $projectId,
        public array $options = []
    ) {}

    public function handle(FFmpegService $ffmpeg, R2StorageService $r2): void
    {
        $project = Project::find($this->projectId);
        if (!$project) {
            Log::error('ComposeVideoJob: Project not found', ['project_id' => $this->projectId]);
            return;
        }

        // Create job log
        $jobLog = JobLog::create([
            'project_id' => $this->projectId,
            'job_type' => 'compose_video',
            'status' => 'running',
            'payload' => $this->options,
            'started_at' => now(),
        ]);

        try {
            // Get assets
            $musicAsset = $project->assets()->where('type', 'music')->first();
            $videoClips = $project->assets()
                ->where('type', 'video_clip')
                ->orderBy('metadata->scene_number')
                ->get();

            if (!$musicAsset) {
                throw new \RuntimeException('No music asset found for project');
            }

            if ($videoClips->isEmpty()) {
                throw new \RuntimeException('No video clips found for project');
            }

            $tempFiles = [];

            // Download music
            Log::info('ComposeVideoJob: Downloading music', ['asset_id' => $musicAsset->id]);
            $musicPath = $ffmpeg->downloadToTemp($musicAsset->url);
            $tempFiles[] = $musicPath;

            // Download video clips
            $videoClipPaths = [];
            foreach ($videoClips as $clip) {
                Log::info('ComposeVideoJob: Downloading video clip', ['asset_id' => $clip->id]);
                $clipPath = $ffmpeg->downloadToTemp($clip->url);
                $videoClipPaths[] = $clipPath;
                $tempFiles[] = $clipPath;
            }

            // Generate output filename
            $outputFilename = 'final_' . Str::uuid() . '.mp4';
            $outputPath = storage_path('app/temp/' . $outputFilename);
            $tempFiles[] = $outputPath;

            // Compose video options
            $composeOptions = [
                'resolution' => $this->options['resolution'] ?? '1920x1080',
                'fps' => $this->options['fps'] ?? 30,
                'video_bitrate' => $this->options['video_bitrate'] ?? '5M',
                'audio_bitrate' => $this->options['audio_bitrate'] ?? '192k',
            ];

            // Check if transitions are enabled
            $useTransitions = $this->options['transitions'] ?? false;
            $transitionType = $this->options['transition_type'] ?? 'fade';
            $transitionDuration = $this->options['transition_duration'] ?? 0.5;

            if ($useTransitions && count($videoClipPaths) > 1) {
                // First add transitions between clips
                $transitionedPath = storage_path('app/temp/transitioned_' . Str::uuid() . '.mp4');
                $tempFiles[] = $transitionedPath;

                Log::info('ComposeVideoJob: Adding transitions', [
                    'transition' => $transitionType,
                    'duration' => $transitionDuration,
                ]);

                $ffmpeg->addTransitions($videoClipPaths, $transitionedPath, $transitionType, $transitionDuration);

                // Then combine with audio
                $combinedPath = storage_path('app/temp/combined_' . Str::uuid() . '.mp4');
                $tempFiles[] = $combinedPath;

                Log::info('ComposeVideoJob: Combining video with audio');
                $success = $ffmpeg->combineVideoWithAudio(
                    [$transitionedPath],
                    $musicPath,
                    $combinedPath,
                    $composeOptions
                );
            } else {
                // Combine video clips with audio directly
                $combinedPath = storage_path('app/temp/combined_' . Str::uuid() . '.mp4');
                $tempFiles[] = $combinedPath;

                Log::info('ComposeVideoJob: Combining videos with audio');
                $success = $ffmpeg->combineVideoWithAudio(
                    $videoClipPaths,
                    $musicPath,
                    $combinedPath,
                    $composeOptions
                );
            }

            if (!$success) {
                throw new \RuntimeException('Failed to combine video with audio');
            }

            // Add lyrics overlay if available
            $concept = $project->concept;
            $addLyrics = $this->options['add_lyrics'] ?? true;

            if ($addLyrics && $concept && !empty($concept['lyrics']) && !empty($concept['lyrics_timing'])) {
                Log::info('ComposeVideoJob: Adding lyrics overlay');

                $lyricsOptions = [
                    'font_size' => $this->options['font_size'] ?? 48,
                    'font_color' => $this->options['font_color'] ?? 'white',
                    'border_color' => $this->options['border_color'] ?? 'black',
                    'border_width' => $this->options['border_width'] ?? 2,
                    'lyrics_position' => $this->options['lyrics_position'] ?? 'bottom',
                ];

                $success = $ffmpeg->addLyricsOverlay(
                    $combinedPath,
                    $outputPath,
                    $concept['lyrics'],
                    $concept['lyrics_timing'],
                    $lyricsOptions
                );

                if (!$success) {
                    Log::warning('ComposeVideoJob: Failed to add lyrics, using video without lyrics');
                    copy($combinedPath, $outputPath);
                }
            } else {
                // No lyrics, just copy combined video
                copy($combinedPath, $outputPath);
            }

            // Get video info
            $videoInfo = $ffmpeg->getVideoInfo($outputPath);

            // Upload to R2
            Log::info('ComposeVideoJob: Uploading final video to R2');
            $r2Path = 'projects/' . $this->projectId . '/final/' . $outputFilename;
            $url = $r2->uploadFile($outputPath, $r2Path);

            // Create final video asset
            $asset = Asset::create([
                'project_id' => $this->projectId,
                'type' => 'final_video',
                'filename' => $outputFilename,
                'url' => $url,
                'size_bytes' => filesize($outputPath),
                'duration_seconds' => (int) $videoInfo['duration'],
                'metadata' => [
                    'resolution' => $composeOptions['resolution'],
                    'fps' => $composeOptions['fps'],
                    'video_bitrate' => $composeOptions['video_bitrate'],
                    'audio_bitrate' => $composeOptions['audio_bitrate'],
                    'has_lyrics' => $addLyrics && !empty($concept['lyrics']),
                    'transitions' => $useTransitions,
                    'video_clips_count' => count($videoClipPaths),
                ],
            ]);

            // Extract thumbnail
            $thumbnailFilename = 'thumbnail_' . Str::uuid() . '.jpg';
            $thumbnailPath = storage_path('app/temp/' . $thumbnailFilename);
            $tempFiles[] = $thumbnailPath;

            if ($ffmpeg->extractThumbnail($outputPath, $thumbnailPath, 1.0)) {
                $thumbnailR2Path = 'projects/' . $this->projectId . '/thumbnails/' . $thumbnailFilename;
                $thumbnailUrl = $r2->uploadFile($thumbnailPath, $thumbnailR2Path);

                Asset::create([
                    'project_id' => $this->projectId,
                    'type' => 'thumbnail',
                    'filename' => $thumbnailFilename,
                    'url' => $thumbnailUrl,
                    'size_bytes' => filesize($thumbnailPath),
                    'metadata' => [
                        'timestamp' => 1.0,
                        'parent_asset_id' => $asset->id,
                    ],
                ]);
            }

            // Cleanup temp files
            $ffmpeg->cleanupTempFiles($tempFiles);

            // Update job log
            $jobLog->update([
                'status' => 'completed',
                'result' => [
                    'asset_id' => $asset->id,
                    'url' => $url,
                    'duration' => $videoInfo['duration'],
                ],
                'completed_at' => now(),
            ]);

            // Update project status
            $project->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('ComposeVideoJob: Completed successfully', [
                'project_id' => $this->projectId,
                'asset_id' => $asset->id,
            ]);

        } catch (\Exception $e) {
            Log::error('ComposeVideoJob: Failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Cleanup temp files on error
            if (!empty($tempFiles)) {
                $ffmpeg->cleanupTempFiles($tempFiles);
            }

            $jobLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $project->update([
                'status' => 'failed',
                'error_message' => 'Video composition failed: ' . $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ComposeVideoJob: Job failed completely', [
            'project_id' => $this->projectId,
            'error' => $e->getMessage(),
        ]);

        $project = Project::find($this->projectId);
        if ($project) {
            $project->update([
                'status' => 'failed',
                'error_message' => 'Video composition failed after retries: ' . $e->getMessage(),
            ]);
        }
    }
}
