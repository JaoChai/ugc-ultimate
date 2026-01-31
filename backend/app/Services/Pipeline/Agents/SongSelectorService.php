<?php

namespace App\Services\Pipeline\Agents;

use App\Models\AgentConfig;

class SongSelectorService extends BaseAgentService
{
    public function getAgentType(): string
    {
        return AgentConfig::TYPE_SONG_SELECTOR;
    }

    /**
     * Execute song selection
     *
     * @param array $input [
     *   'song_concept' => array (from SongArchitect),
     *   'suno_result' => array (from SunoExpert with versions array)
     * ]
     */
    public function execute(array $input): array
    {
        $songConcept = $input['song_concept'] ?? [];
        $sunoResult = $input['suno_result'] ?? [];
        $versions = $sunoResult['versions'] ?? [];

        $this->logInfo("Starting song selection process");
        $this->logProgress(10, "Analyzing generated versions...");

        // Check if we have versions to select from
        if (empty($versions)) {
            $this->logError("No versions available for selection");
            throw new \RuntimeException('No song versions available for selection');
        }

        $this->logInfo("Found " . count($versions) . " version(s) to evaluate");

        // If only one version, select it automatically
        if (count($versions) === 1) {
            $this->logInfo("Only one version available, selecting automatically");
            return $this->createSingleVersionResult($versions[0], $songConcept);
        }

        // Step 1: Prepare evaluation data
        $this->logThinking("Evaluating song versions...");
        $this->logProgress(30, "Comparing versions...");

        $userPrompt = $this->buildEvaluationPrompt($songConcept, $versions);

        // Step 2: Call LLM for evaluation
        $this->logProgress(50, "Getting AI evaluation...");

        $evaluation = $this->callLlm($userPrompt);

        // Step 3: Process selection
        $this->logProgress(70, "Processing selection...");

        $selectedIndex = $evaluation['selected_index'] ?? 0;

        // Validate selected index
        if ($selectedIndex < 0 || $selectedIndex >= count($versions)) {
            $this->logInfo("Invalid selected index {$selectedIndex}, defaulting to 0");
            $selectedIndex = 0;
        }

        $selectedVersion = $versions[$selectedIndex];

        $result = [
            'selected_index' => $selectedIndex,
            'selected_audio_url' => $selectedVersion['audio_url'] ?? null,
            'selected_clip_id' => $selectedVersion['clip_id'] ?? null,
            'selected_duration' => $selectedVersion['duration'] ?? null,
            'evaluation' => $evaluation['evaluation'] ?? [],
            'selection_reasoning' => $evaluation['selection_reasoning'] ?? 'Default selection',
            'recommendation' => $evaluation['recommendation'] ?? 'Proceed with selected version',
        ];

        $this->logResult("Song selection completed", [
            'selected_index' => $selectedIndex,
            'reasoning' => $result['selection_reasoning'],
        ]);

        $this->logProgress(100, "Song selected - ready for visual design");

        return $result;
    }

    protected function buildEvaluationPrompt(array $songConcept, array $versions): string
    {
        $conceptTitle = $songConcept['song_title'] ?? 'Unknown';
        $conceptGenre = $songConcept['genre'] ?? 'pop';
        $conceptMood = $songConcept['mood'] ?? 'neutral';
        $conceptHook = $songConcept['hook'] ?? '';

        $versionsInfo = [];
        foreach ($versions as $index => $version) {
            $versionsInfo[] = [
                'index' => $index,
                'duration' => $version['duration'] ?? 'unknown',
                'title' => $version['title'] ?? 'unknown',
                'has_audio_url' => !empty($version['audio_url']),
            ];
        }

        $versionsJson = json_encode($versionsInfo, JSON_PRETTY_PRINT);

        return <<<PROMPT
Evaluate and select the best song version.

## Original Song Concept
- Title: {$conceptTitle}
- Genre: {$conceptGenre}
- Mood: {$conceptMood}
- Hook: {$conceptHook}

## Available Versions
{$versionsJson}

## Your Task
1. Evaluate each version based on metadata
2. Score each version (0-100)
3. Select the best version
4. Provide detailed reasoning

Remember: You cannot listen to the audio, so evaluate based on:
- Duration appropriateness (2-4 minutes ideal)
- Completion status
- Alignment with original concept
- If all else is equal, select version 0
PROMPT;
    }

    protected function createSingleVersionResult(array $version, array $songConcept): array
    {
        $this->logProgress(100, "Single version selected");

        return [
            'selected_index' => 0,
            'selected_audio_url' => $version['audio_url'] ?? null,
            'selected_clip_id' => $version['clip_id'] ?? null,
            'selected_duration' => $version['duration'] ?? null,
            'evaluation' => [
                'version_0' => [
                    'total_score' => 100,
                    'criteria_scores' => [
                        'concept_alignment' => 25,
                        'technical_quality' => 25,
                        'hook_potential' => 25,
                        'production_consistency' => 25,
                    ],
                    'strengths' => ['Only available version'],
                    'concerns' => [],
                ],
            ],
            'selection_reasoning' => 'Only one version was generated, automatically selected.',
            'recommendation' => 'Proceed with this version. Consider regenerating if audio quality is unsatisfactory.',
        ];
    }
}
