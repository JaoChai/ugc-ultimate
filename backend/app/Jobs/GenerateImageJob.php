<?php

namespace App\Jobs;

use App\Models\ApiKey;
use App\Models\Asset;
use App\Models\JobLog;
use App\Models\Project;
use App\Services\ImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 300;

    public function __construct(
        protected int $projectId,
        protected array $config
    ) {}

    public function handle(ImageService $image): void
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
            'job_type' => 'generate_image',
            'status' => 'running',
            'payload' => $this->config,
            'started_at' => now(),
        ]);

        try {
            // Set API key
            $image->setApiKey($apiKey);

            // Get provider
            $provider = $this->config['provider'] ?? ImageService::PROVIDER_FLUX;

            // Generate image
            $result = $image->generate(
                prompt: $this->config['prompt'],
                provider: $provider,
                options: [
                    'aspect_ratio' => $this->config['aspect_ratio'] ?? '16:9',
                    'model' => $this->config['model'] ?? null,
                    'style' => $this->config['style'] ?? null,
                ]
            );

            $taskId = $result['task_id'] ?? null;

            if (!$taskId) {
                throw new \RuntimeException('No task ID returned from Image API');
            }

            // Create asset record to track
            $asset = Asset::create([
                'project_id' => $this->projectId,
                'type' => 'image',
                'filename' => ($this->config['name'] ?? 'image') . '.png',
                'url' => '', // Will be updated by webhook
                'kie_task_id' => $taskId,
                'metadata' => [
                    'config' => $this->config,
                    'provider' => $provider,
                    'image_result' => $result,
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

            Log::info('Image generation started', [
                'project_id' => $this->projectId,
                'task_id' => $taskId,
                'asset_id' => $asset->id,
                'provider' => $provider,
            ]);
        } catch (\Exception $e) {
            $jobLog->markAsFailed($e->getMessage());

            Log::error('Image generation failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateImageJob failed', [
            'project_id' => $this->projectId,
            'config' => $this->config,
            'error' => $exception->getMessage(),
        ]);

        // Update project status
        Project::where('id', $this->projectId)->update([
            'status' => 'failed',
            'error_message' => 'Image generation failed: ' . $exception->getMessage(),
        ]);
    }
}
