<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'type',
        'filename',
        'url',
        'size_bytes',
        'duration_seconds',
        'metadata',
        'kie_task_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeMusic($query)
    {
        return $query->where('type', 'music');
    }

    public function scopeImage($query)
    {
        return $query->where('type', 'image');
    }

    public function scopeVideoClip($query)
    {
        return $query->where('type', 'video_clip');
    }

    public function scopeFinalVideo($query)
    {
        return $query->where('type', 'final_video');
    }
}
