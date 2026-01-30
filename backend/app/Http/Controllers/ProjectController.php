<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateConceptJob;
use App\Jobs\GenerateImageJob;
use App\Jobs\GenerateMusicJob;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()->projects()
            ->with('channel:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'channel_id' => ['nullable', 'exists:channels,id'],
            'theme' => ['nullable', 'string', 'max:500'],
        ]);

        $project = $request->user()->projects()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'channel_id' => $validated['channel_id'] ?? null,
            'status' => 'draft',
        ]);

        return response()->json([
            'message' => 'Project created successfully',
            'project' => $project,
        ], 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load(['channel:id,name', 'assets', 'jobLogs']);

        return response()->json(['project' => $project]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'channel_id' => ['nullable', 'exists:channels,id'],
        ]);

        $project->update($validated);

        return response()->json([
            'message' => 'Project updated successfully',
            'project' => $project,
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    /**
     * Generate AI concept for project
     */
    public function generateConcept(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'theme' => ['nullable', 'string', 'max:500'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:180'],
            'audience' => ['nullable', 'string', 'in:general,kids,teens,adults'],
            'platform' => ['nullable', 'string', 'in:YouTube,TikTok,Instagram'],
            'language' => ['nullable', 'string', 'max:50'],
            'scene_count' => ['nullable', 'integer', 'min:2', 'max:10'],
            'aspect_ratio' => ['nullable', 'string', 'in:16:9,9:16,1:1'],
            'visual_style' => ['nullable', 'string', 'max:100'],
            'auto_generate' => ['nullable', 'boolean'],
        ]);

        // Update project status
        $project->update(['status' => 'processing']);

        // Dispatch concept generation job
        GenerateConceptJob::dispatch($project->id, array_merge($validated, [
            'theme' => $validated['theme'] ?? $project->title,
        ]));

        return response()->json([
            'message' => 'Concept generation started',
            'project' => $project->fresh(),
        ]);
    }

    /**
     * Generate music for project
     */
    public function generateMusic(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'prompt' => ['required_without:use_concept', 'string', 'max:1000'],
            'lyrics' => ['nullable', 'string', 'max:5000'],
            'title' => ['nullable', 'string', 'max:255'],
            'style' => ['nullable', 'string', 'max:100'],
            'instrumental' => ['nullable', 'boolean'],
            'use_concept' => ['nullable', 'boolean'],
            'model' => ['nullable', 'string', 'in:chirp-v3-5,chirp-v4,chirp-v4-5,chirp-v4-5-plus,chirp-v5'],
        ]);

        // If using concept, get prompt from project concept
        if ($validated['use_concept'] ?? false) {
            $concept = $project->concept;
            if (!$concept) {
                return response()->json(['error' => 'No concept generated yet'], 400);
            }
            $validated['prompt'] = $concept['suno_prompt'] ?? '';
            $validated['lyrics'] = $concept['lyrics'] ?? null;
            $validated['title'] = $concept['music']['title'] ?? $project->title;
            $validated['style'] = $concept['music']['genre'] ?? 'pop';
        }

        // Update project status
        $project->update(['status' => 'processing']);

        // Dispatch music generation job
        GenerateMusicJob::dispatch($project->id, $validated);

        return response()->json([
            'message' => 'Music generation started',
            'project' => $project->fresh(),
        ]);
    }

    /**
     * Generate images for project
     */
    public function generateImages(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'prompts' => ['required_without:use_concept', 'array'],
            'prompts.*.prompt' => ['required', 'string', 'max:10000'],
            'prompts.*.name' => ['nullable', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'in:nano-banana,nano-banana-pro'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,2:3,3:2,4:3,9:16,16:9,21:9'],
            'resolution' => ['nullable', 'string', 'in:1K,2K,4K'],
            'use_concept' => ['nullable', 'boolean'],
        ]);

        // If using concept, get prompts from project concept
        if ($validated['use_concept'] ?? false) {
            $concept = $project->concept;
            if (!$concept || empty($concept['image_prompts'])) {
                return response()->json(['error' => 'No image prompts in concept'], 400);
            }
            $validated['prompts'] = array_map(fn($p) => [
                'prompt' => $p['prompt'],
                'name' => 'scene_' . $p['scene_number'],
            ], $concept['image_prompts']);
        }

        // Update project status
        $project->update(['status' => 'processing']);

        // Dispatch image generation jobs
        foreach ($validated['prompts'] as $imageConfig) {
            GenerateImageJob::dispatch($project->id, array_merge($imageConfig, [
                'provider' => $validated['provider'] ?? 'nano-banana-pro',
                'aspect_ratio' => $validated['aspect_ratio'] ?? '16:9',
                'resolution' => $validated['resolution'] ?? '1K',
            ]));
        }

        return response()->json([
            'message' => 'Image generation started',
            'image_count' => count($validated['prompts']),
            'project' => $project->fresh(),
        ]);
    }

    /**
     * Get project status and progress
     */
    public function status(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load('jobLogs');

        $jobStats = [
            'total' => $project->jobLogs->count(),
            'pending' => $project->jobLogs->where('status', 'pending')->count(),
            'running' => $project->jobLogs->where('status', 'running')->count(),
            'completed' => $project->jobLogs->where('status', 'completed')->count(),
            'failed' => $project->jobLogs->where('status', 'failed')->count(),
        ];

        $assetStats = [
            'music' => $project->assets()->where('type', 'music')->count(),
            'images' => $project->assets()->where('type', 'image')->count(),
        ];

        return response()->json([
            'project' => $project,
            'jobs' => $jobStats,
            'assets' => $assetStats,
            'progress' => $jobStats['total'] > 0
                ? round(($jobStats['completed'] / $jobStats['total']) * 100)
                : 0,
        ]);
    }

    /**
     * Get project assets
     */
    public function assets(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $assets = $project->assets()->orderBy('created_at', 'desc')->get();

        return response()->json(['assets' => $assets]);
    }

    /**
     * Generate all content for project (full workflow)
     * Pipeline: Concept → Music + Images (parallel)
     */
    public function generateAll(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'theme' => ['nullable', 'string', 'max:500'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:180'],
            'audience' => ['nullable', 'string', 'in:general,kids,teens,adults'],
            'platform' => ['nullable', 'string', 'in:YouTube,TikTok,Instagram'],
            'language' => ['nullable', 'string', 'max:50'],
            'scene_count' => ['nullable', 'integer', 'min:2', 'max:10'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,2:3,3:2,4:3,9:16,16:9,21:9'],
            'visual_style' => ['nullable', 'string', 'max:100'],
            'music_provider' => ['nullable', 'string', 'in:suno'],
            'image_provider' => ['nullable', 'string', 'in:nano-banana,nano-banana-pro'],
            'image_resolution' => ['nullable', 'string', 'in:1K,2K,4K'],
        ]);

        // Update project status
        $project->update(['status' => 'processing']);

        // Store workflow options in project concept for later use
        $project->update([
            'concept' => array_merge($project->concept ?? [], [
                '_workflow_options' => $validated,
            ]),
        ]);

        // Dispatch concept generation with auto_generate flag
        GenerateConceptJob::dispatch($project->id, array_merge($validated, [
            'theme' => $validated['theme'] ?? $project->title,
            'auto_generate' => true,
        ]));

        return response()->json([
            'message' => 'Full generation workflow started',
            'project' => $project->fresh(),
            'workflow' => [
                'step_1' => 'Generating AI concept...',
                'step_2' => 'Will generate music automatically (Suno v5)',
                'step_3' => 'Will generate images automatically (Nano Banana)',
            ],
        ]);
    }

    /**
     * Download assets (music or images)
     */
    public function download(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $assets = $project->assets()->get();

        if ($assets->isEmpty()) {
            return response()->json([
                'error' => 'No assets found. Please generate content first.',
            ], 404);
        }

        return response()->json([
            'assets' => $assets->map(fn($asset) => [
                'type' => $asset->type,
                'download_url' => $asset->url,
                'filename' => $asset->filename,
                'size_bytes' => $asset->size_bytes,
            ]),
        ]);
    }
}
