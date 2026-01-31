<?php

namespace App\Http\Controllers;

use App\Jobs\Pipeline\RunMusicVideoPipelineJob;
use App\Jobs\Pipeline\RunPipelineJob;
use App\Jobs\Pipeline\RunPipelineStepJob;
use App\Models\ApiKey;
use App\Models\Pipeline;
use App\Models\Project;
use App\Services\Pipeline\PipelineOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function __construct(
        protected PipelineOrchestratorService $orchestrator
    ) {}

    /**
     * List all pipelines for the user
     */
    public function index(Request $request): JsonResponse
    {
        $pipelines = Pipeline::where('user_id', $request->user()->id)
            ->with(['project:id,title'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($pipelines);
    }

    /**
     * Create a new pipeline for a project
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'pipeline_type' => ['nullable', 'string', 'in:video,music_video'],
            'mode' => ['nullable', 'string', 'in:auto,manual'],
            'theme' => ['nullable', 'string', 'max:500'],
            'song_brief' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:300'],
            'platform' => ['nullable', 'string', 'in:youtube,tiktok,instagram'],
        ]);

        // Check if project already has an active pipeline
        $activePipeline = Pipeline::where('project_id', $project->id)
            ->whereIn('status', [Pipeline::STATUS_PENDING, Pipeline::STATUS_RUNNING, Pipeline::STATUS_PAUSED])
            ->first();

        if ($activePipeline) {
            return response()->json([
                'error' => 'Project already has an active pipeline',
                'pipeline' => $activePipeline,
            ], 400);
        }

        $pipelineType = $validated['pipeline_type'] ?? Pipeline::TYPE_VIDEO;

        // Build config based on pipeline type
        $config = [
            'theme' => $validated['theme'] ?? $project->title,
            'duration' => $validated['duration'] ?? 60,
            'platform' => $validated['platform'] ?? 'youtube',
        ];

        // For music_video pipeline, add song_brief
        if ($pipelineType === Pipeline::TYPE_MUSIC_VIDEO) {
            $config['song_brief'] = $validated['song_brief'] ?? $validated['theme'] ?? $project->title;
        }

        $pipeline = Pipeline::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'pipeline_type' => $pipelineType,
            'mode' => $validated['mode'] ?? Pipeline::MODE_AUTO,
            'status' => Pipeline::STATUS_PENDING,
            'config' => $config,
            'steps_state' => [],
        ]);

        return response()->json([
            'message' => 'Pipeline created successfully',
            'pipeline' => $pipeline,
        ], 201);
    }

    /**
     * Get pipeline details
     */
    public function show(Pipeline $pipeline): JsonResponse
    {
        $this->authorize('view', $pipeline);

        $pipeline->load(['project:id,title', 'logs' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(100);
        }]);

        return response()->json(['pipeline' => $pipeline]);
    }

    /**
     * Start the pipeline
     */
    public function start(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

        if ($pipeline->status !== Pipeline::STATUS_PENDING) {
            return response()->json([
                'error' => 'Pipeline cannot be started from current state',
                'current_status' => $pipeline->status,
            ], 400);
        }

        // Get API keys
        $openRouterKey = ApiKey::where('user_id', $request->user()->id)
            ->where('service', 'openrouter')
            ->where('is_active', true)
            ->first();

        $kieKey = ApiKey::where('user_id', $request->user()->id)
            ->where('service', 'kie')
            ->where('is_active', true)
            ->first();

        if (!$openRouterKey) {
            return response()->json(['error' => 'OpenRouter API key not found'], 400);
        }

        if (!$kieKey) {
            return response()->json(['error' => 'Kie API key not found'], 400);
        }

        // Dispatch pipeline job based on type
        if ($pipeline->mode === Pipeline::MODE_AUTO) {
            if ($pipeline->pipeline_type === Pipeline::TYPE_MUSIC_VIDEO) {
                // Use Music Video Pipeline
                RunMusicVideoPipelineJob::dispatch($pipeline->id, $openRouterKey->id, $kieKey->id);
            } else {
                // Use standard Video Pipeline
                RunPipelineJob::dispatch($pipeline->id, $openRouterKey->id, $kieKey->id);
            }
        } else {
            // For manual mode, just mark as running and wait for step commands
            $pipeline->update([
                'status' => Pipeline::STATUS_RUNNING,
                'started_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Pipeline started',
            'pipeline' => $pipeline->fresh(),
        ]);
    }

    /**
     * Pause the pipeline
     */
    public function pause(Pipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

        if ($pipeline->status !== Pipeline::STATUS_RUNNING) {
            return response()->json(['error' => 'Pipeline is not running'], 400);
        }

        $this->orchestrator->pausePipeline($pipeline);

        return response()->json([
            'message' => 'Pipeline paused',
            'pipeline' => $pipeline->fresh(),
        ]);
    }

    /**
     * Resume the pipeline
     */
    public function resume(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

        if ($pipeline->status !== Pipeline::STATUS_PAUSED) {
            return response()->json(['error' => 'Pipeline is not paused'], 400);
        }

        // Get API keys
        $openRouterKey = ApiKey::where('user_id', $request->user()->id)
            ->where('service', 'openrouter')
            ->where('is_active', true)
            ->first();

        $kieKey = ApiKey::where('user_id', $request->user()->id)
            ->where('service', 'kie')
            ->where('is_active', true)
            ->first();

        if (!$openRouterKey || !$kieKey) {
            return response()->json(['error' => 'Required API keys not found'], 400);
        }

        // Resume by running the next step
        $nextStep = $pipeline->getNextStep();
        if ($nextStep) {
            RunPipelineStepJob::dispatch($pipeline->id, $nextStep, $openRouterKey->id, $kieKey->id);
        }

        return response()->json([
            'message' => 'Pipeline resumed',
            'pipeline' => $pipeline->fresh(),
        ]);
    }

    /**
     * Cancel the pipeline
     */
    public function cancel(Pipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

        if (in_array($pipeline->status, [Pipeline::STATUS_COMPLETED, Pipeline::STATUS_FAILED])) {
            return response()->json(['error' => 'Pipeline already finished'], 400);
        }

        $this->orchestrator->cancelPipeline($pipeline);

        return response()->json([
            'message' => 'Pipeline cancelled',
            'pipeline' => $pipeline->fresh(),
        ]);
    }

    /**
     * Run a specific step (manual mode)
     */
    public function runStep(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

        if ($pipeline->mode !== Pipeline::MODE_MANUAL) {
            return response()->json(['error' => 'Pipeline is not in manual mode'], 400);
        }

        $validated = $request->validate([
            'step' => ['required', 'string', 'in:' . implode(',', Pipeline::STEPS)],
        ]);

        // Get API keys
        $openRouterKey = ApiKey::where('user_id', $request->user()->id)
            ->where('service', 'openrouter')
            ->where('is_active', true)
            ->first();

        $kieKey = ApiKey::where('user_id', $request->user()->id)
            ->where('service', 'kie')
            ->where('is_active', true)
            ->first();

        if (!$openRouterKey || !$kieKey) {
            return response()->json(['error' => 'Required API keys not found'], 400);
        }

        // Dispatch step job
        RunPipelineStepJob::dispatch($pipeline->id, $validated['step'], $openRouterKey->id, $kieKey->id);

        return response()->json([
            'message' => "Running step: {$validated['step']}",
            'pipeline' => $pipeline->fresh(),
        ]);
    }

    /**
     * Get pipeline logs
     */
    public function logs(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('view', $pipeline);

        $logs = $pipeline->logs()
            ->when($request->get('agent_type'), function ($query, $agentType) {
                $query->where('agent_type', $agentType);
            })
            ->when($request->get('log_type'), function ($query, $logType) {
                $query->where('log_type', $logType);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($logs);
    }

    /**
     * Get step result
     */
    public function stepResult(Pipeline $pipeline, string $step): JsonResponse
    {
        $this->authorize('view', $pipeline);

        // Get valid steps based on pipeline type
        $validSteps = $pipeline->getSteps();

        if (!in_array($step, $validSteps)) {
            return response()->json(['error' => 'Invalid step for this pipeline type'], 400);
        }

        $stepState = $pipeline->getStepState($step);

        return response()->json([
            'step' => $step,
            'state' => $stepState,
        ]);
    }

    /**
     * Get pipeline steps based on type
     */
    public function getSteps(Pipeline $pipeline): JsonResponse
    {
        $this->authorize('view', $pipeline);

        return response()->json([
            'pipeline_type' => $pipeline->pipeline_type,
            'steps' => $pipeline->getSteps(),
        ]);
    }
}
