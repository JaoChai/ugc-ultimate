<?php

namespace App\Services\AI;

class PromptTemplates
{
    /**
     * System prompt for music concept generation
     */
    public static function musicConceptSystem(): string
    {
        return <<<PROMPT
You are a creative music director and songwriter. Your job is to create compelling music concepts for video content.

When given a theme or topic, you will generate:
1. A catchy song title
2. The musical style/genre that best fits the theme
3. The mood and energy level
4. A brief description of the song's vibe
5. Key musical elements (instruments, tempo, etc.)

Be creative, original, and consider what would work well in a short-form video (TikTok, YouTube Shorts, Reels).

Always respond in valid JSON format.
PROMPT;
    }

    /**
     * Prompt for generating music concept
     */
    public static function musicConcept(string $theme, array $options = []): string
    {
        $duration = $options['duration'] ?? '60 seconds';
        $targetAudience = $options['audience'] ?? 'general';
        $platform = $options['platform'] ?? 'YouTube';
        $language = $options['language'] ?? 'English';

        return <<<PROMPT
Create a music concept for a video about: "{$theme}"

Requirements:
- Duration: approximately {$duration}
- Target audience: {$targetAudience}
- Platform: {$platform}
- Language for lyrics: {$language}

Generate a JSON response with this structure:
{
    "title": "Song title",
    "genre": "Primary genre",
    "subGenre": "Sub-genre or style variation",
    "mood": "Overall mood (e.g., upbeat, melancholic, energetic)",
    "tempo": "BPM range (e.g., 120-130)",
    "energy": "low/medium/high",
    "instruments": ["List of key instruments"],
    "description": "Brief description of the sound",
    "keywords": ["Style keywords for music generation"],
    "lyricThemes": ["Themes to explore in lyrics"]
}
PROMPT;
    }

    /**
     * System prompt for lyrics generation
     */
    public static function lyricsSystem(): string
    {
        return <<<PROMPT
You are an expert songwriter who creates catchy, memorable lyrics. Your lyrics are:
- Easy to sing and remember
- Emotionally resonant
- Appropriate for the target audience
- Well-structured with clear verses, choruses, and bridges

When writing lyrics:
1. Keep verses concise (4-8 lines each)
2. Make the chorus hook memorable and repeatable
3. Use rhyme schemes that feel natural
4. Include rhythm and flow suitable for the musical style
5. Avoid clichés while keeping the message accessible

Always format lyrics with clear section markers ([Verse 1], [Chorus], etc.)
PROMPT;
    }

    /**
     * Prompt for generating lyrics
     */
    public static function lyrics(array $concept, array $options = []): string
    {
        $title = $concept['title'] ?? 'Untitled';
        $genre = $concept['genre'] ?? 'Pop';
        $mood = $concept['mood'] ?? 'upbeat';
        $themes = implode(', ', $concept['lyricThemes'] ?? ['life', 'dreams']);
        $language = $options['language'] ?? 'English';
        $duration = $options['duration'] ?? 60;

        // Estimate structure based on duration
        $structure = $duration <= 30 ? 'Verse + Chorus' : ($duration <= 60 ? 'Verse + Chorus + Verse' : 'Intro + Verse + Chorus + Verse + Chorus + Bridge + Outro');

        return <<<PROMPT
Write lyrics for a song with these specifications:

Title: {$title}
Genre: {$genre}
Mood: {$mood}
Themes to explore: {$themes}
Language: {$language}
Target duration: {$duration} seconds
Suggested structure: {$structure}

Requirements:
1. Write complete lyrics with section markers
2. Make the chorus catchy and memorable
3. Keep the language appropriate for all audiences
4. Match the mood and energy of the genre
5. Include timing hints in brackets if needed (e.g., [pause], [build])

Format your response as:
[Section Name]
Lyrics here...

[Next Section]
More lyrics...
PROMPT;
    }

    /**
     * System prompt for visual concept generation
     */
    public static function visualConceptSystem(): string
    {
        return <<<PROMPT
You are a creative director specializing in music video visuals and short-form video content.

Your expertise includes:
- Creating compelling visual narratives
- Designing scenes that match musical mood and energy
- Understanding what works on social media platforms
- Creating prompts for AI image and video generation

When creating visual concepts:
1. Consider the pacing of the music
2. Create visually striking, shareable moments
3. Ensure scenes flow naturally from one to the next
4. Design for the vertical (9:16) and horizontal (16:9) formats
5. Include specific visual details that AI can generate well

Always provide detailed, specific descriptions suitable for AI image/video generation.
PROMPT;
    }

    /**
     * Prompt for generating visual concepts
     */
    public static function visualConcept(array $musicConcept, string $lyrics, array $options = []): string
    {
        $title = $musicConcept['title'] ?? 'Untitled';
        $mood = $musicConcept['mood'] ?? 'upbeat';
        $genre = $musicConcept['genre'] ?? 'Pop';
        $sceneCount = $options['scene_count'] ?? 4;
        $aspectRatio = $options['aspect_ratio'] ?? '16:9';
        $style = $options['visual_style'] ?? 'cinematic';

        return <<<PROMPT
Create visual concepts for a music video:

Song: {$title}
Genre: {$genre}
Mood: {$mood}
Visual style: {$style}
Aspect ratio: {$aspectRatio}
Number of scenes needed: {$sceneCount}

Lyrics:
{$lyrics}

Generate a JSON response with this structure:
{
    "overallStyle": "Description of the overall visual style",
    "colorPalette": ["Primary colors to use"],
    "scenes": [
        {
            "sceneNumber": 1,
            "duration": "estimated seconds",
            "section": "Which part of the song (intro/verse/chorus)",
            "description": "What happens in this scene",
            "imagePrompt": "Detailed prompt for AI image generation",
            "videoPrompt": "Detailed prompt for AI video generation",
            "cameraMovement": "Camera movement description",
            "transition": "How to transition to next scene"
        }
    ],
    "textOverlays": [
        {
            "text": "Text to display",
            "timing": "When to show",
            "style": "Text style description"
        }
    ]
}

Make image/video prompts very specific and detailed for best AI generation results.
Include lighting, composition, atmosphere, and specific visual elements.
PROMPT;
    }

    /**
     * Prompt for generating image prompts from lyrics
     */
    public static function imagePromptFromLyrics(string $lyricLine, string $style, string $mood): string
    {
        return <<<PROMPT
Create an AI image generation prompt for this lyric line:

Lyric: "{$lyricLine}"
Visual style: {$style}
Mood: {$mood}

Generate a detailed image prompt that:
1. Captures the emotion and meaning of the lyric
2. Fits the visual style
3. Would look stunning as a music video frame
4. Works well with AI image generators (Flux, Midjourney)

Include:
- Subject and composition
- Lighting and atmosphere
- Color palette
- Camera angle/perspective
- Style modifiers (cinematic, dramatic, etc.)

Respond with only the image prompt, no explanations.
PROMPT;
    }

    /**
     * Prompt for video scene description
     */
    public static function videoScenePrompt(string $imageDescription, string $movement, int $duration): string
    {
        return <<<PROMPT
Convert this image description into a video generation prompt:

Image: {$imageDescription}
Desired movement/action: {$movement}
Duration: {$duration} seconds

Create a prompt for AI video generation (Kling/Runway) that:
1. Describes the starting frame (based on image)
2. Specifies the motion and movement
3. Describes how the scene evolves
4. Maintains visual consistency

Be specific about:
- Subject motion (how people/objects move)
- Camera motion (pan, zoom, dolly, etc.)
- Atmospheric changes (if any)
- Pacing (slow/medium/fast)

Respond with only the video prompt, no explanations.
PROMPT;
    }

    /**
     * JSON schema for music concept response
     */
    public static function musicConceptSchema(): array
    {
        return [
            'name' => 'music_concept',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'genre' => ['type' => 'string'],
                    'subGenre' => ['type' => 'string'],
                    'mood' => ['type' => 'string'],
                    'tempo' => ['type' => 'string'],
                    'energy' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                    'instruments' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'description' => ['type' => 'string'],
                    'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'lyricThemes' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['title', 'genre', 'mood', 'energy', 'description'],
            ],
        ];
    }

    /**
     * JSON schema for visual concept response
     */
    public static function visualConceptSchema(): array
    {
        return [
            'name' => 'visual_concept',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'overallStyle' => ['type' => 'string'],
                    'colorPalette' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'scenes' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'sceneNumber' => ['type' => 'integer'],
                                'duration' => ['type' => 'string'],
                                'section' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'imagePrompt' => ['type' => 'string'],
                                'videoPrompt' => ['type' => 'string'],
                                'cameraMovement' => ['type' => 'string'],
                                'transition' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'textOverlays' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'text' => ['type' => 'string'],
                                'timing' => ['type' => 'string'],
                                'style' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'required' => ['overallStyle', 'scenes'],
            ],
        ];
    }
}
