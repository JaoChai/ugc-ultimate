<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $channels = $request->user()->channels()
            ->withCount('projects')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['channels' => $channels]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:youtube,tiktok,instagram'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $channel = $request->user()->channels()->create($validated);

        return response()->json([
            'message' => 'Channel created successfully',
            'channel' => $channel,
        ], 201);
    }

    public function show(Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $channel->load(['projects' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }]);
        $channel->loadCount('projects');

        return response()->json(['channel' => $channel]);
    }

    public function update(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('update', $channel);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:youtube,tiktok,instagram'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $channel->update($validated);

        return response()->json([
            'message' => 'Channel updated successfully',
            'channel' => $channel,
        ]);
    }

    public function destroy(Channel $channel): JsonResponse
    {
        $this->authorize('delete', $channel);

        $channel->delete();

        return response()->json(['message' => 'Channel deleted successfully']);
    }

    /**
     * Get channel statistics
     */
    public function stats(Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $stats = [
            'total_projects' => $channel->projects()->count(),
            'completed_projects' => $channel->projects()->where('status', 'completed')->count(),
            'processing_projects' => $channel->projects()->where('status', 'processing')->count(),
            'failed_projects' => $channel->projects()->where('status', 'failed')->count(),
            'scheduled_tasks' => $channel->scheduledTasks()->where('is_active', true)->count(),
        ];

        return response()->json(['stats' => $stats]);
    }

    /**
     * Configure schedule for channel
     */
    public function updateSchedule(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('update', $channel);

        $validated = $request->validate([
            'schedule_config' => ['required', 'array'],
            'schedule_config.enabled' => ['required', 'boolean'],
            'schedule_config.cron' => ['required_if:schedule_config.enabled,true', 'string'],
            'schedule_config.timezone' => ['nullable', 'string', 'timezone'],
            'schedule_config.theme' => ['nullable', 'string', 'max:500'],
            'schedule_config.duration' => ['nullable', 'integer', 'min:15', 'max:180'],
            'schedule_config.audience' => ['nullable', 'string', 'in:general,kids,teens,adults'],
            'schedule_config.platform' => ['nullable', 'string', 'in:YouTube,TikTok,Instagram'],
            'schedule_config.language' => ['nullable', 'string', 'max:50'],
            'schedule_config.visual_style' => ['nullable', 'string', 'max:100'],
        ]);

        $channel->update([
            'schedule_config' => $validated['schedule_config'],
        ]);

        // Update or create scheduled task
        if ($validated['schedule_config']['enabled']) {
            $channel->scheduledTasks()->updateOrCreate(
                ['channel_id' => $channel->id],
                [
                    'cron_expression' => $validated['schedule_config']['cron'],
                    'next_run_at' => $this->calculateNextRun($validated['schedule_config']['cron']),
                    'is_active' => true,
                    'config' => $validated['schedule_config'],
                ]
            );
        } else {
            $channel->scheduledTasks()->update(['is_active' => false]);
        }

        return response()->json([
            'message' => 'Schedule updated successfully',
            'channel' => $channel->fresh(),
        ]);
    }

    /**
     * Calculate next run time from cron expression
     */
    protected function calculateNextRun(string $cron): \DateTime
    {
        // Simple cron parsing - for production use a library like dragonmantank/cron-expression
        $parts = explode(' ', $cron);
        $now = new \DateTime();

        // Default: next hour
        $next = clone $now;
        $next->modify('+1 hour');
        $next->setTime((int) $next->format('H'), 0, 0);

        // Handle common patterns
        if (count($parts) === 5) {
            $minute = $parts[0];
            $hour = $parts[1];

            if ($minute !== '*' && $hour !== '*') {
                $next->setTime((int) $hour, (int) $minute, 0);
                if ($next <= $now) {
                    $next->modify('+1 day');
                }
            }
        }

        return $next;
    }
}
