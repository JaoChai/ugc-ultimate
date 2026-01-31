<?php

namespace App\Jobs;

use App\Exceptions\R2StorageException;
use App\Models\Asset;
use App\Models\JobLog;
use App\Models\Project;
use App\Services\KieApiService;
use App\Services\R2StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessKieWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected array $payload,
        protected ?int $assetId = null,
        protected ?int $jobLogId = null
    ) {}

    public function handle(R2StorageService $r2): void
    {
        $status = $this->payload['status'] ?? $this->payload['state'] ?? null;
        $taskId = $this->payload['task_id'] ?? $this->payload['id'] ?? null;

        Log::info('Processing kie.ai webhook', [
            'task_id' => $taskId,
            'status' => $status,
            'asset_id' => $this->assetId,
            'job_log_id' => $this->jobLogId,
        ]);

        // Update job log if exists
        if ($this->jobLogId) {
            $jobLog = JobLog::find($this->jobLogId);

            if ($jobLog) {
                $this->updateJobLog($jobLog, $status);
            }
        }

        // Update asset if exists
        if ($this->assetId) {
            $asset = Asset::find($this->assetId);

            if ($asset) {
                $this->updateAsset($asset, $status, $r2);
            }
        }

        // Check if all assets for a project are complete
        $this->checkProjectCompletion();
    }

    protected function updateJobLog(JobLog $jobLog, ?string $status): void
    {
        $mappedStatus = match ($status) {
            'completed', 'success', 'done' => 'completed',
            'failed', 'error' => 'failed',
            'processing', 'running', 'pending' => 'running',
            default => $jobLog->status,
        };

        $updateData = [
            'status' => $mappedStatus,
            'result' => $this->payload,
        ];

        if ($mappedStatus === 'completed' || $mappedStatus === 'failed') {
            $updateData['completed_at'] = now();
        }

        if ($mappedStatus === 'failed') {
            $updateData['error_message'] = $this->payload['error'] ?? $this->payload['message'] ?? 'Task failed';
        }

        $jobLog->update($updateData);
    }

    protected function updateAsset(Asset $asset, ?string $status, R2StorageService $r2): void
    {
        if (!in_array($status, ['completed', 'success', 'done'])) {
            return;
        }

        // Get the output URL from webhook payload
        $outputUrl = $this->payload['output_url']
            ?? $this->payload['url']
            ?? $this->payload['result']['url']
            ?? $this->payload['data']['url']
            ?? null;

        if (!$outputUrl) {
            Log::warning('No output URL in webhook payload', ['payload' => $this->payload]);
            return;
        }

        try {
            // Determine file extension from URL
            $extension = pathinfo(parse_url($outputUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: $this->guessExtension($asset->type);

            // Generate R2 path
            $r2Path = $r2->generateAssetPath(
                $asset->project_id,
                $asset->type,
                $extension
            );

            // Upload to R2
            $permanentUrl = $r2->uploadFromUrl($outputUrl, $r2Path);

            // Update asset with permanent URL
            $asset->update([
                'url' => $permanentUrl,
                'metadata' => array_merge($asset->metadata ?? [], [
                    'original_url' => $outputUrl,
                    'webhook_data' => $this->payload,
                    'processed_at' => now()->toISOString(),
                ]),
            ]);

            Log::info('Asset uploaded to R2', [
                'asset_id' => $asset->id,
                'url' => $permanentUrl,
            ]);
        } catch (R2StorageException $e) {
            // R2 storage errors - re-throw to trigger job retry
            Log::warning('R2 upload failed, job will retry', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'is_retryable' => $e->isRetryable(),
                'attempt' => $this->attempts(),
            ]);

            throw $e; // Re-throw to trigger job retry
        } catch (\Exception $e) {
            // Other errors - log and mark asset as failed but don't retry
            Log::error('Failed to process asset (non-retryable)', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            // Update asset to indicate failure
            $asset->update([
                'metadata' => array_merge($asset->metadata ?? [], [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ]),
            ]);
        }
    }

    protected function checkProjectCompletion(): void
    {
        if (!$this->assetId) {
            return;
        }

        $asset = Asset::find($this->assetId);
        if (!$asset) {
            return;
        }

        $project = $asset->project;
        if (!$project || $project->status !== 'processing') {
            return;
        }

        // Check if all pending jobs are complete
        $pendingJobs = $project->jobLogs()
            ->whereIn('status', ['pending', 'running'])
            ->count();

        if ($pendingJobs === 0) {
            // Check if any jobs failed
            $failedJobs = $project->jobLogs()
                ->where('status', 'failed')
                ->count();

            if ($failedJobs > 0) {
                $project->update([
                    'status' => 'failed',
                    'error_message' => 'One or more generation jobs failed',
                ]);
            } else {
                $project->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            Log::info('Project status updated', [
                'project_id' => $project->id,
                'status' => $project->status,
            ]);
        }
    }

    protected function guessExtension(string $type): string
    {
        return match ($type) {
            'music' => 'mp3',
            'image' => 'png',
            'video_clip', 'final_video' => 'mp4',
            default => 'bin',
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessKieWebhookJob failed', [
            'payload' => $this->payload,
            'asset_id' => $this->assetId,
            'job_log_id' => $this->jobLogId,
            'error' => $exception->getMessage(),
        ]);
    }
}
