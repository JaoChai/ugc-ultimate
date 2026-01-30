<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessKieWebhookJob;
use App\Models\Asset;
use App\Models\JobLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle kie.ai webhook callback
     */
    public function handleKie(Request $request): JsonResponse
    {
        // Verify webhook signature if secret is configured
        $secret = config('services.kie.webhook_secret');
        if ($secret) {
            $signature = $request->header('X-Kie-Signature');
            $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

            if (!hash_equals($expectedSignature, $signature ?? '')) {
                Log::warning('Invalid kie.ai webhook signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();

        Log::info('kie.ai webhook received', ['payload' => $payload]);

        // Extract task info
        $taskId = $payload['task_id'] ?? $payload['id'] ?? null;
        $status = $payload['status'] ?? $payload['state'] ?? null;
        $type = $payload['type'] ?? $payload['service'] ?? 'unknown';

        if (!$taskId) {
            return response()->json(['error' => 'Missing task_id'], 400);
        }

        // Find the related asset or job log
        $asset = Asset::where('kie_task_id', $taskId)->first();
        $jobLog = JobLog::where('payload->task_id', $taskId)->first();

        if (!$asset && !$jobLog) {
            Log::warning('kie.ai webhook: No matching asset or job found', ['task_id' => $taskId]);
            return response()->json(['message' => 'No matching task found'], 200);
        }

        // Dispatch job to process the webhook asynchronously
        ProcessKieWebhookJob::dispatch($payload, $asset?->id, $jobLog?->id);

        return response()->json(['message' => 'Webhook received']);
    }

    /**
     * Handle R2 event notifications (optional)
     */
    public function handleR2(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('R2 webhook received', ['payload' => $payload]);

        // Process R2 events (upload complete, delete, etc.)
        $eventType = $payload['event'] ?? null;
        $key = $payload['key'] ?? null;

        // Handle specific events if needed

        return response()->json(['message' => 'Webhook received']);
    }
}
