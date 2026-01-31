<?php

namespace App\Services\Pipeline\Agents;

use App\Models\AgentConfig;

class SongArchitectService extends BaseAgentService
{
    public function getAgentType(): string
    {
        return AgentConfig::TYPE_SONG_ARCHITECT;
    }

    /**
     * Execute song architecture design
     *
     * @param array $input [
     *   'song_brief' => string (user's description of the song they want)
     * ]
     */
    public function execute(array $input): array
    {
        $songBrief = $input['song_brief'] ?? '';

        $this->logInfo("Starting song architecture design");
        $this->logProgress(10, "Analyzing song brief...");

        // Step 1: Analyze the brief and generate song concept
        $this->logThinking("Understanding the song requirements...");

        $userPrompt = <<<PROMPT
Create a complete song concept based on this brief:

{$songBrief}

Design the full song structure, write compelling lyrics, identify the hook, and derive a memorable title from it.

Remember:
- The hook should be 3-7 words and be the most catchy line
- The title MUST come from the hook
- Total duration should be 2-4 minutes
- Use the same language as the brief for lyrics
PROMPT;

        $this->logProgress(30, "Designing song structure...");

        $result = $this->callLlm($userPrompt);

        $this->logProgress(70, "Finalizing song concept...");

        // Validate required fields
        $this->validateResult($result);

        $this->logProgress(90, "Song architecture complete");

        $this->logResult("Song concept created successfully", [
            'title' => $result['song_title'] ?? 'Untitled',
            'hook' => $result['hook'] ?? '',
            'genre' => $result['genre'] ?? 'pop',
        ]);

        $this->logProgress(100, "Ready for Suno optimization");

        return $result;
    }

    protected function validateResult(array $result): void
    {
        $requiredFields = ['song_structure', 'full_lyrics', 'hook', 'song_title', 'genre', 'mood'];

        foreach ($requiredFields as $field) {
            if (empty($result[$field])) {
                $this->logError("Missing required field: {$field}");
            }
        }

        // Validate hook length
        $hook = $result['hook'] ?? '';
        $wordCount = str_word_count($hook);
        if ($wordCount < 2 || $wordCount > 10) {
            $this->logInfo("Hook word count ({$wordCount}) outside ideal range (3-7 words)");
        }
    }
}
