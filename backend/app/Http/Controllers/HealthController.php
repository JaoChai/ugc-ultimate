<?php

namespace App\Http\Controllers;

use App\Models\JobLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * Basic health check
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Queue health check
     */
    public function queue(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        // Check Redis connection
        try {
            $redisStatus = Redis::ping();
            $health['checks']['redis'] = [
                'status' => 'ok',
                'message' => 'Connected',
            ];
        } catch (\Exception $e) {
            $health['status'] = 'degraded';
            $health['checks']['redis'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Check pending jobs in database queue
        try {
            $pendingJobs = DB::table('jobs')->count();
            $health['checks']['pending_jobs'] = [
                'status' => $pendingJobs > 100 ? 'warning' : 'ok',
                'count' => $pendingJobs,
                'message' => $pendingJobs > 100 ? 'High number of pending jobs' : 'Normal',
            ];
        } catch (\Exception $e) {
            $health['checks']['pending_jobs'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Check failed jobs in last 24 hours
        try {
            $failedJobs = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();

            $health['checks']['failed_jobs_24h'] = [
                'status' => $failedJobs > 10 ? 'warning' : 'ok',
                'count' => $failedJobs,
                'message' => $failedJobs > 10 ? 'High number of failed jobs' : 'Normal',
            ];
        } catch (\Exception $e) {
            $health['checks']['failed_jobs_24h'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Check job_logs status
        try {
            $jobLogStats = JobLog::selectRaw("status, COUNT(*) as count")
                ->where('created_at', '>=', now()->subDay())
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $runningJobs = $jobLogStats['running'] ?? 0;
            $stuckRunningJobs = JobLog::where('status', 'running')
                ->where('started_at', '<', now()->subHour())
                ->count();

            $health['checks']['job_logs_24h'] = [
                'status' => $stuckRunningJobs > 0 ? 'warning' : 'ok',
                'stats' => $jobLogStats,
                'stuck_running' => $stuckRunningJobs,
                'message' => $stuckRunningJobs > 0
                    ? "{$stuckRunningJobs} jobs stuck in running state"
                    : 'Normal',
            ];
        } catch (\Exception $e) {
            $health['checks']['job_logs_24h'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Check stale projects
        try {
            $staleProjects = Project::where('status', 'processing')
                ->where('updated_at', '<', now()->subMinutes(30))
                ->count();

            $health['checks']['stale_projects'] = [
                'status' => $staleProjects > 0 ? 'warning' : 'ok',
                'count' => $staleProjects,
                'message' => $staleProjects > 0
                    ? "{$staleProjects} projects stuck in processing"
                    : 'No stale projects',
            ];
        } catch (\Exception $e) {
            $health['checks']['stale_projects'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Overall status
        $hasErrors = collect($health['checks'])->contains(fn($check) => $check['status'] === 'error');
        $hasWarnings = collect($health['checks'])->contains(fn($check) => $check['status'] === 'warning');

        if ($hasErrors) {
            $health['status'] = 'error';
        } elseif ($hasWarnings) {
            $health['status'] = 'warning';
        }

        $statusCode = $health['status'] === 'error' ? 503 : 200;

        return response()->json($health, $statusCode);
    }

    /**
     * Database health check
     */
    public function database(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'ok',
                'message' => 'Database connected',
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }
    }
}
