<?php

namespace App\Http\Controllers;

use App\Jobs\ComposeVideoJob;
use App\Jobs\GenerateConceptJob;
use App\Jobs\GenerateImageJob;
use App\Jobs\GenerateMusicJob;
use App\Jobs\GenerateVideoJob;
use App\Models\Project;
use App\Services\AI\ConceptGeneratorService;
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
            'prompts.*.prompt' => ['required', 'string', 'max:1000'],
            'prompts.*.name' => ['nullable', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'in:flux,midjourney,dalle,ideogram'],
            'aspect_ratio' => ['nullable', 'string', 'in:16:9,9:16,1:1'],
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
                'provider' => $validated['provider'] ?? 'flux',
                'aspect_ratio' => $validated['aspect_ratio'] ?? '16:9',
            ]));
        }

        return response()->json([
            'message' => 'Image generation started',
            'image_count' => count($validated['prompts']),
            'project' => $project->fresh(),
        ]);
    }

    /**
     * Generate videos for project
     */
    public function generateVideos(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'prompts' => ['required_without:use_concept', 'array'],
            'prompts.*.prompt' => ['required', 'string', 'max:1000'],
            'prompts.*.image_url' => ['nullable', 'url'],
            'prompts.*.name' => ['nullable', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'in:kling,hailuo,runway'],
            'duration' => ['nullable', 'integer', 'min:5', 'max:10'],
            'aspect_ratio' => ['nullable', 'string', 'in:16:9,9:16,1:1'],
            'use_concept' => ['nullable', 'boolean'],
        ]);

        // If using concept and images exist, use them
        if ($validated['use_concept'] ?? false) {
            $concept = $project->concept;
            if (!$concept || empty($concept['video_prompts'])) {
                return response()->json(['error' => 'No video prompts in concept'], 400);
            }

            // Get image assets to use as reference
            $imageAssets = $project->assets()->where('type', 'image')->get()->keyBy(function ($asset) {
                preg_match('/scene_(\d+)/', $asset->filename, $matches);
                return $matches[1] ?? 0;
            });

            $validated['prompts'] = array_map(function ($p) use ($imageAssets) {
                return [
                    'prompt' => $p['prompt'],
                    'name' => 'video_scene_' . $p['scene_number'],
                    'image_url' => $imageAssets[$p['scene_number']]->url ?? null,
                ];
            }, $concept['video_prompts']);
        }

        // Update project status
        $project->update(['status' => 'processing']);

        // Dispatch video generation jobs
        foreach ($validated['prompts'] as $videoConfig) {
            GenerateVideoJob::dispatch($project->id, array_merge($videoConfig, [
                'provider' => $validated['provider'] ?? 'kling',
                'duration' => $validated['duration'] ?? 5,
                'aspect_ratio' => $validated['aspect_ratio'] ?? '16:9',
            ]));
        }

        return response()->json([
            'message' => 'Video generation started',
            'video_count' => count($validated['prompts']),
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
            'video_clips' => $project->assets()->where('type', 'video_clip')->count(),
            'final_video' => $project->assets()->where('type', 'final_video')->count(),
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
     * Compose final video from assets
     */
    public function compose(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        // Check if we have required assets
        $hasMusicAsset = $project->assets()->where('type', 'music')->exists();
        $videoClipsCount = $project->assets()->where('type', 'video_clip')->count();

        if (!$hasMusicAsset) {
            return response()->json([
                'error' => 'No music asset found. Please generate music first.',
            ], 400);
        }

        if ($videoClipsCount === 0) {
            return response()->json([
                'error' => 'No video clips found. Please generate videos first.',
            ], 400);
        }

        $validated = $request->validate([
            'resolution' => ['nullable', 'string', 'in:1920x1080,1080x1920,1080x1080'],
            'fps' => ['nullable', 'integer', 'min:24', 'max:60'],
            'video_bitrate' => ['nullable', 'string', 'regex:/^\d+[KMG]$/i'],
            'audio_bitrate' => ['nullable', 'string', 'regex:/^\d+k$/i'],
            'transitions' => ['nullable', 'boolean'],
            'transition_type' => ['nullable', 'string', 'in:fade,wipeleft,wiperight,slideup,slidedown,circleopen,circleclose'],
            'transition_duration' => ['nullable', 'numeric', 'min:0.1', 'max:2.0'],
            'add_lyrics' => ['nullable', 'boolean'],
            'font_size' => ['nullable', 'integer', 'min:12', 'max:120'],
            'font_color' => ['nullable', 'string', 'max:20'],
            'border_color' => ['nullable', 'string', 'max:20'],
            'border_width' => ['nullable', 'integer', 'min:0', 'max:10'],
            'lyrics_position' => ['nullable', 'string', 'in:top,bottom,center'],
        ]);

        // Update project status
        $project->update(['status' => 'processing']);

        // Dispatch compose job
        ComposeVideoJob::dispatch($project->id, $validated);

        return response()->json([
            'message' => 'Video composition started',
            'project' => $project->fresh(),
            'assets_info' => [
                'music' => 1,
                'video_clips' => $videoClipsCount,
            ],
        ]);
    }

    /**
     * Re-compose video with different options
     */
    public function recompose(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        // Delete existing final video assets
        $project->assets()->whereIn('type', ['final_video', 'thumbnail'])->delete();

        // Call compose with new options
        return $this->compose($request, $project);
    }

    /**
     * Generate all content for project (full workflow)
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
            'aspect_ratio' => ['nullable', 'string', 'in:16:9,9:16,1:1'],
            'visual_style' => ['nullable', 'string', 'max:100'],
            'music_provider' => ['nullable', 'string', 'in:suno'],
            'video_provider' => ['nullable', 'string', 'in:kling,hailuo,runway'],
            'image_provider' => ['nullable', 'string', 'in:flux,midjourney,dalle,ideogram'],
            'auto_compose' => ['nullable', 'boolean'],
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
                'step_2' => 'Will generate music automatically',
                'step_3' => 'Will generate images automatically',
                'step_4' => 'Will generate videos automatically',
                'step_5' => $validated['auto_compose'] ?? true ? 'Will compose final video automatically' : 'Manual compose required',
            ],
        ]);
    }

    /**
     * Download final video
     */
    public function download(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $finalVideo = $project->assets()->where('type', 'final_video')->first();

        if (!$finalVideo) {
            return response()->json([
                'error' => 'No final video found. Please compose the video first.',
            ], 404);
        }

        return response()->json([
            'download_url' => $finalVideo->url,
            'filename' => $finalVideo->filename,
            'size_bytes' => $finalVideo->size_bytes,
            'duration_seconds' => $finalVideo->duration_seconds,
        ]);
    }
}
