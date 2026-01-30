<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'mode',
        'status',
        'current_step',
        'current_step_progress',
        'config',
        'steps_state',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'steps_state' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Pipeline steps in order
    public const STEPS = [
        'theme_director',
        'music_composer',
        'visual_director',
        'image_generator',
        'video_composer',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    // Mode constants
    public const MODE_AUTO = 'auto';
    public const MODE_MANUAL = 'manual';

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PipelineLog::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    // Helpers
    public function getNextStep(): ?string
    {
        if (!$this->current_step) {
            return self::STEPS[0];
        }

        $currentIndex = array_search($this->current_step, self::STEPS);
        if ($currentIndex === false || $currentIndex >= count(self::STEPS) - 1) {
            return null;
        }

        return self::STEPS[$currentIndex + 1];
    }

    public function getStepState(string $step): array
    {
        return $this->steps_state[$step] ?? [
            'status' => 'pending',
            'progress' => 0,
            'result' => null,
            'error' => null,
        ];
    }

    public function updateStepState(string $step, array $data): void
    {
        $stepsState = $this->steps_state ?? [];
        $stepsState[$step] = array_merge($this->getStepState($step), $data);
        $this->update(['steps_state' => $stepsState]);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    // Scopes
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
