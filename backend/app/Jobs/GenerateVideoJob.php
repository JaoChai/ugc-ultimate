<?php

namespace App\Jobs;

use App\Models\ApiKey;
use App\Models\Asset;
use App\Models\JobLog;
use App\Models\Project;
use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;
    public int $timeout = 900;

    public function __construct(
        protected int $projectId,
        protected array $config
    ) {}

    public function handle(VideoService $video): void
    {
        $project = Project::findOrFail($this->projectId);

        // Get kie.ai API key
        $apiKey = ApiKey::where('user_id', $project->user_id)
            ->where('service', 'kie')
            ->where('is_active', true)
            ->first();

        if (!$apiKey) {
            throw new \RuntimeException('No active kie.ai API key found');
        }

        // Create job log
        $jobLog = JobLog::create([
            'project_id' => $this->projectId,
            'job_type' => 'generate_video',
            'status' => 'running',
            'payload' => $this->config,
            'started_at' => now(),
        ]);

        try {
            // Set API key
            $video->setApiKey($apiKey);

            // Get provider
            $provider = $this->config['provider'] ?? VideoService::PROVIDER_KLING;

            // Generate video
            $result = $video->generate(
                prompt: $this->config['prompt'],
                imageUrl: $this->config['image_url'] ?? null,
                provider: $provider,
                options: [
                    'aspect_ratio' => $this->config['aspect_ratio'] ?? '16:9',
                    'duration' => $this->config['duration'] ?? 5,
                    'mode' => $this->config['mode'] ?? 'standard',
                ]
            );

            $taskId = $result['task_id'] ?? null;

            if (!$taskId) {
                throw new \RuntimeException('No task ID returned from Video API');
            }

            // Create asset record to track
            $asset = Asset::create([
                'project_id' => $this->projectId,
                'type' => 'video_clip',
                'filename' => ($this->config['name'] ?? 'video') . '.mp4',
                'url' => '', // Will be updated by webhook
                'duration_seconds' => $this->config['duration'] ?? 5,
                'kie_task_id' => $taskId,
                'metadata' => [
                    'config' => $this->config,
                    'provider' => $provider,
                    'video_result' => $result,
                ],
            ]);

            // Update job log
            $jobLog->update([
                'result' => [
                    'task_id' => $taskId,
                    'asset_id' => $asset->id,
                    'provider' => $provider,
                ],
            ]);

            Log::info('Video generation started', [
                'project_id' => $this->projectId,
                'task_id' => $taskId,
                'asset_id' => $asset->id,
                'provider' => $provider,
            ]);
        } catch (\Exception $e) {
            $jobLog->markAsFailed($e->getMessage());

            Log::error('Video generation failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateVideoJob failed', [
            'project_id' => $this->projectId,
            'config' => $this->config,
            'error' => $exception->getMessage(),
        ]);

        // Update project status
        Project::where('id', $this->projectId)->update([
            'status' => 'failed',
            'error_message' => 'Video generation failed: ' . $exception->getMessage(),
        ]);
    }
}
