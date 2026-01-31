<?php

namespace App\Console\Commands;

use App\Models\JobLog;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStaleProjects extends Command
{
    protected $signature = 'projects:cleanup-stale
                            {--dry-run : Show what would be cleaned up without making changes}
                            {--stale-minutes=30 : Minutes before a processing project is considered stale}
                            {--job-timeout=60 : Minutes before a running job is considered stale}';

    protected $description = 'Cleanup projects stuck in processing status';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $staleMinutes = (int) $this->option('stale-minutes');
        $jobTimeout = (int) $this->option('job-timeout');

        $this->info($dryRun ? '[DRY RUN] ' : '' . 'Checking for stale projects...');

        // Find projects stuck in 'processing' for too long
        $staleProjects = Project::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($staleMinutes))
            ->get();

        $this->info("Found {$staleProjects->count()} projects in processing status for more than {$staleMinutes} minutes");

        $cleaned = 0;

        foreach ($staleProjects as $project) {
            $result = $this->analyzeAndCleanProject($project, $jobTimeout, $dryRun);
            if ($result) {
                $cleaned++;
            }
        }

        // Also check for stale job_logs
        $staleJobs = JobLog::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes($jobTimeout))
            ->get();

        if ($staleJobs->count() > 0) {
            $this->info("Found {$staleJobs->count()} jobs stuck in running status");

            foreach ($staleJobs as $jobLog) {
                $this->cleanupStaleJob($jobLog, $dryRun);
            }
        }

        $this->info($dryRun ? "[DRY RUN] Would have cleaned {$cleaned} projects" : "Cleaned {$cleaned} projects");

        Log::info('CleanupStaleProjects completed', [
            'dry_run' => $dryRun,
            'stale_projects_found' => $staleProjects->count(),
            'projects_cleaned' => $cleaned,
            'stale_jobs_found' => $staleJobs->count(),
        ]);

        return 0;
    }

    protected function analyzeAndCleanProject(Project $project, int $jobTimeout, bool $dryRun): bool
    {
        $jobLogs = $project->jobLogs;

        // Case 1: No job_logs at all - job was never executed
        if ($jobLogs->isEmpty()) {
            $errorMessage = 'Job was never executed. Queue worker may have been unavailable.';
            $this->warn("Project {$project->id} ({$project->title}): {$errorMessage}");

            if (!$dryRun) {
                $project->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);
            }
            return true;
        }

        // Case 2: Has job_logs but all completed - should have updated status
        $allCompleted = $jobLogs->every(fn($log) => $log->status === 'completed');
        if ($allCompleted) {
            $this->warn("Project {$project->id}: All jobs completed but status not updated. Fixing...");

            if (!$dryRun) {
                $project->update(['status' => 'completed']);
            }
            return true;
        }

        // Case 3: Has failed jobs
        $hasFailedJobs = $jobLogs->contains(fn($log) => $log->status === 'failed');
        if ($hasFailedJobs) {
            $failedJob = $jobLogs->firstWhere('status', 'failed');
            $errorMessage = $failedJob->error_message ?? 'Job failed without error message';

            $this->warn("Project {$project->id}: Has failed jobs. Marking as failed.");

            if (!$dryRun) {
                $project->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);
            }
            return true;
        }

        // Case 4: Jobs stuck in 'running' for too long
        $stuckRunningJobs = $jobLogs->filter(fn($log) =>
            $log->status === 'running' &&
            $log->started_at &&
            $log->started_at < now()->subMinutes($jobTimeout)
        );

        if ($stuckRunningJobs->isNotEmpty()) {
            $errorMessage = "Jobs stuck in running state for more than {$jobTimeout} minutes";
            $this->warn("Project {$project->id}: {$errorMessage}");

            if (!$dryRun) {
                // Mark stuck jobs as failed
                foreach ($stuckRunningJobs as $jobLog) {
                    $jobLog->markAsFailed('Job timed out - stuck in running state');
                }

                $project->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);
            }
            return true;
        }

        // Case 5: Jobs still pending - might be waiting in queue
        $pendingJobs = $jobLogs->filter(fn($log) => $log->status === 'pending');
        if ($pendingJobs->isNotEmpty()) {
            $this->info("Project {$project->id}: Has {$pendingJobs->count()} pending jobs - may still be in queue");
            return false;
        }

        return false;
    }

    protected function cleanupStaleJob(JobLog $jobLog, bool $dryRun): void
    {
        $this->warn("JobLog {$jobLog->id} (project: {$jobLog->project_id}, type: {$jobLog->job_type}) stuck in running");

        if (!$dryRun) {
            $jobLog->markAsFailed('Job timed out - marked as failed by cleanup command');

            Log::warning('CleanupStaleProjects: Marked job as failed', [
                'job_log_id' => $jobLog->id,
                'project_id' => $jobLog->project_id,
                'job_type' => $jobLog->job_type,
            ]);
        }
    }
}
