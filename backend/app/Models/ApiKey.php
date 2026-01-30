<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service',
        'name',
        'key_encrypted',
        'credits_remaining',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credits_remaining' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'key_encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
