<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'platform',
        'description',
        'schedule_config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'schedule_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ScheduledTask::class);
    }
}
