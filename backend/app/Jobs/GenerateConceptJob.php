<?php

namespace App\Jobs;

use App\Models\ApiKey;
use App\Models\JobLog;
use App\Models\Project;
use App\Services\AI\ConceptGeneratorService;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateConceptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 300;

    public function __construct(
        protected int $projectId,
        protected array $config
    ) {}

    public function handle(ConceptGeneratorService $conceptGenerator): void
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
            'job_type' => 'generate_concept',
            'status' => 'running',
            'payload' => $this->config,
            'started_at' => now(),
        ]);

        try {
            // Set API key
            $conceptGenerator->setApiKey($apiKey);

            // Get theme from config or project
            $theme = $this->config['theme'] ?? $project->title;

            // Generate options
            $options = [
                'duration' => $this->config['duration'] ?? 60,
                'audience' => $this->config['audience'] ?? 'general',
                'platform' => $this->config['platform'] ?? 'YouTube',
                'language' => $this->config['language'] ?? 'English',
                'scene_count' => $this->config['scene_count'] ?? 4,
                'aspect_ratio' => $this->config['aspect_ratio'] ?? '16:9',
                'visual_style' => $this->config['visual_style'] ?? 'cinematic',
                'thinking_level' => $this->config['thinking_level'] ?? GeminiService::THINKING_MEDIUM,
            ];

            // Generate full concept
            $concept = $conceptGenerator->generateFullConcept($theme, $options);

            // Build Suno prompt
            $concept['suno_prompt'] = $conceptGenerator->buildSunoPrompt($concept['music']);

            // Generate image and video prompts
            $concept['image_prompts'] = $conceptGenerator->generateImagePrompts($concept['visual']);
            $concept['video_prompts'] = $conceptGenerator->generateVideoPrompts($concept['visual']);

            // Update project with concept
            $project->update([
                'concept' => $concept,
            ]);

            // Update job log
            $jobLog->markAsCompleted([
                'concept_generated' => true,
                'music_concept' => $concept['music'],
                'scene_count' => count($concept['visual']['scenes'] ?? []),
            ]);

            Log::info('Concept generated successfully', [
                'project_id' => $this->projectId,
                'title' => $concept['music']['title'] ?? 'Unknown',
            ]);

            // Optionally dispatch next jobs (music, images, videos)
            if ($this->config['auto_generate'] ?? false) {
                $this->dispatchGenerationJobs($project, $concept);
            }

        } catch (\Exception $e) {
            $jobLog->markAsFailed($e->getMessage());

            Log::error('Concept generation failed', [
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Dispatch jobs for music, image, and video generation
     */
    protected function dispatchGenerationJobs(Project $project, array $concept): void
    {
        // Dispatch music generation
        GenerateMusicJob::dispatch($project->id, [
            'prompt' => $concept['suno_prompt'],
            'lyrics' => $concept['lyrics'],
            'title' => $concept['music']['title'] ?? $project->title,
            'style' => $concept['music']['genre'] ?? 'pop',
        ]);

        // Dispatch image generation for each scene
        foreach ($concept['image_prompts'] ?? [] as $imagePrompt) {
            GenerateImageJob::dispatch($project->id, [
                'prompt' => $imagePrompt['prompt'],
                'name' => 'scene_' . $imagePrompt['scene_number'],
                'provider' => $this->config['image_provider'] ?? 'flux',
                'aspect_ratio' => $this->config['aspect_ratio'] ?? '16:9',
            ]);
        }

        Log::info('Generation jobs dispatched', [
            'project_id' => $project->id,
            'image_count' => count($concept['image_prompts'] ?? []),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateConceptJob failed', [
            'project_id' => $this->projectId,
            'config' => $this->config,
            'error' => $exception->getMessage(),
        ]);

        // Update project status
        Project::where('id', $this->projectId)->update([
            'status' => 'failed',
            'error_message' => 'Concept generation failed: ' . $exception->getMessage(),
        ]);
    }
}
