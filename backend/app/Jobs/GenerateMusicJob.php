<?php

namespace App\Jobs;

use App\Models\ApiKey;
use App\Models\Asset;
use App\Models\JobLog;
use App\Models\Project;
use App\Services\SunoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMusicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;
    public int $timeout = 600;

    public function __construct(
        protected int $projectId,
        protected array $config
    ) {}

    public function handle(SunoService $suno): void
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
            'job_type' => 'generate_music',
            'status' => 'running',
            'payload' => $this->config,
            'started_at' => now(),
        ]);

        try {
            // Set API key
            $suno->setApiKey($apiKey);

            // Generate music
            $result = $suno->generate(
                prompt: $this->config['prompt'],
                lyrics: $this->config['lyrics'] ?? null,
                title: $this->config['title'] ?? null,
                style: $this->config['style'] ?? null,
                instrumental: $this->config['instrumental'] ?? false
            );

            $taskId = $result['task_id'] ?? null;

            if (!$taskId) {
                throw new \RuntimeException('No task ID returned from Suno API');
            }

            // Create asset record to track
            $asset = Asset::create([
                'project_id' => $this->projectId,
                'type' => 'music',
                'filename' => ($this->config['title'] ?? 'music') . '.mp3',
                'url' => '', // Will be updated by webhook
                'kie_task_id' => $taskId,
                'metadata' => [
                    'config' => $this->config,
                    'suno_result' => $result,
                ],
            ]);

            // Update job log
            $jobLog->update([
                'result' => [
                    'task_id' => $taskId,
                    'asset_id' => $asset->id,
                ],
            ]);

            Log::info('Music generation started', [
                'project_id' => $this->projectId,
                'task_id' => $taskId,
                'asset_id' => $asset->id,
            ]);
        } catch (\Exception $e) {
            $jobLog->markAsFailed($e->getMessage());

            Log::error('Music generation failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateMusicJob failed', [
            'project_id' => $this->projectId,
            'config' => $this->config,
            'error' => $exception->getMessage(),
        ]);

        // Update project status
        Project::where('id', $this->projectId)->update([
            'status' => 'failed',
            'error_message' => 'Music generation failed: ' . $exception->getMessage(),
        ]);
    }
}
