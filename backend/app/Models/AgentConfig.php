<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agent_type',
        'name',
        'system_prompt',
        'model',
        'parameters',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'is_default' => 'boolean',
        ];
    }

    // Agent type constants
    public const TYPE_THEME_DIRECTOR = 'theme_director';
    public const TYPE_MUSIC_COMPOSER = 'music_composer';
    public const TYPE_VISUAL_DIRECTOR = 'visual_director';
    public const TYPE_IMAGE_GENERATOR = 'image_generator';
    public const TYPE_VIDEO_COMPOSER = 'video_composer';

    public const AGENT_TYPES = [
        self::TYPE_THEME_DIRECTOR,
        self::TYPE_MUSIC_COMPOSER,
        self::TYPE_VISUAL_DIRECTOR,
        self::TYPE_IMAGE_GENERATOR,
        self::TYPE_VIDEO_COMPOSER,
    ];

    // Default models
    public const DEFAULT_MODEL = 'google/gemini-2.0-flash-exp';

    // Default parameters
    public const DEFAULT_PARAMETERS = [
        'temperature' => 0.7,
        'max_tokens' => 2000,
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helpers
    public function getTemperature(): float
    {
        return $this->parameters['temperature'] ?? self::DEFAULT_PARAMETERS['temperature'];
    }

    public function getMaxTokens(): int
    {
        return $this->parameters['max_tokens'] ?? self::DEFAULT_PARAMETERS['max_tokens'];
    }

    public function getModel(): string
    {
        return $this->model ?? self::DEFAULT_MODEL;
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('agent_type', $type);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Static helpers
    public static function getDefaultForUser(int $userId, string $agentType): ?self
    {
        return self::forUser($userId)
            ->ofType($agentType)
            ->default()
            ->first();
    }

    public static function getOrCreateDefault(int $userId, string $agentType): self
    {
        $config = self::getDefaultForUser($userId, $agentType);

        if (!$config) {
            $config = self::create([
                'user_id' => $userId,
                'agent_type' => $agentType,
                'name' => 'Default',
                'system_prompt' => self::getDefaultSystemPrompt($agentType),
                'model' => self::DEFAULT_MODEL,
                'parameters' => self::DEFAULT_PARAMETERS,
                'is_default' => true,
            ]);
        }

        return $config;
    }

    public static function getDefaultSystemPrompt(string $agentType): string
    {
        return match ($agentType) {
            self::TYPE_THEME_DIRECTOR => self::getThemeDirectorPrompt(),
            self::TYPE_MUSIC_COMPOSER => self::getMusicComposerPrompt(),
            self::TYPE_VISUAL_DIRECTOR => self::getVisualDirectorPrompt(),
            self::TYPE_IMAGE_GENERATOR => self::getImageGeneratorPrompt(),
            self::TYPE_VIDEO_COMPOSER => self::getVideoComposerPrompt(),
            default => '',
        };
    }

    private static function getThemeDirectorPrompt(): string
    {
        return <<<'PROMPT'
You are a Creative Director specializing in YouTube content creation.

Your task is to analyze the given theme and generate a comprehensive concept.

Output must be valid JSON with this structure:
{
  "title": "Catchy title for the video",
  "description": "Brief description of the content",
  "mood": "Primary mood (e.g., happy, nostalgic, energetic, calm)",
  "style": "Visual style (e.g., anime, realistic, abstract, cinematic)",
  "target_audience": "Target audience description",
  "keywords": ["keyword1", "keyword2", "keyword3"],
  "color_palette": ["#color1", "#color2", "#color3"]
}

Be creative but practical. The concept should work well for a music video.
PROMPT;
    }

    private static function getMusicComposerPrompt(): string
    {
        return <<<'PROMPT'
You are a Music Producer who creates songs for Suno AI music generation.

Your task is to create a music concept and write lyrics based on the theme.

Output must be valid JSON with this structure:
{
  "suno_prompt": "Detailed prompt for Suno (include genre, mood, instruments, tempo description)",
  "title": "Song title",
  "genre": "Music genre",
  "bpm": 120,
  "lyrics": "Full lyrics with verse/chorus structure",
  "lyrics_segments": [
    {"section": "intro", "start": 0, "end": 8, "text": "..."},
    {"section": "verse1", "start": 8, "end": 24, "text": "..."},
    {"section": "chorus", "start": 24, "end": 40, "text": "..."}
  ]
}

Rules:
- Lyrics should match the specified duration
- Segment timestamps should be realistic
- The suno_prompt should be descriptive and specific
PROMPT;
    }

    private static function getVisualDirectorPrompt(): string
    {
        return <<<'PROMPT'
You are a Visual Director who creates storyboards for music videos.

Your task is to create scene descriptions and image prompts that sync with the lyrics.

Output must be valid JSON with this structure:
{
  "scenes": [
    {
      "number": 1,
      "section": "intro",
      "duration": 5,
      "image_prompt": "Detailed prompt for AI image generation...",
      "description": "What happens in this scene",
      "transition": "fade"
    }
  ],
  "style_guide": {
    "art_style": "Consistent art style for all scenes",
    "color_palette": ["#color1", "#color2"],
    "character_consistency": "Description for maintaining character consistency"
  }
}

Rules:
- Each scene should be 4-6 seconds
- Image prompts should be detailed and specific
- Maintain visual consistency across all scenes
- Available transitions: fade, slide, zoom, dissolve
PROMPT;
    }

    private static function getImageGeneratorPrompt(): string
    {
        return <<<'PROMPT'
You are an Image Quality Controller.

Your task is to review the generated images and provide feedback.

For each image, evaluate:
- Adherence to the prompt
- Visual quality
- Consistency with style guide
- Suitability for video use

Output feedback as JSON:
{
  "scene_number": 1,
  "quality_score": 8,
  "issues": ["any issues found"],
  "suggestions": ["improvement suggestions"],
  "approved": true
}
PROMPT;
    }

    private static function getVideoComposerPrompt(): string
    {
        return <<<'PROMPT'
You are a Video Editor who composes final videos.

Your task is to provide composition instructions for FFmpeg.

Based on the scenes and music, specify:
- Scene order and durations
- Transition types and timing
- Ken Burns effect parameters
- Audio sync points

Output as JSON:
{
  "composition": [
    {
      "scene": 1,
      "duration": 5,
      "transition_in": "fade",
      "transition_out": "slide",
      "ken_burns": {"zoom": 1.1, "direction": "up"}
    }
  ],
  "audio_sync": {
    "fade_in": 2,
    "fade_out": 2
  }
}
PROMPT;
    }
}
