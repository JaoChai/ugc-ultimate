<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'cron_expression',
        'next_run_at',
        'last_run_at',
        'is_active',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('next_run_at', '<=', now());
    }

    public function markAsRun(): void
    {
        $this->update([
            'last_run_at' => now(),
        ]);
    }
}
