<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'pipeline_id',
        'agent_type',
        'log_type',
        'message',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    // Log type constants
    public const TYPE_INFO = 'info';
    public const TYPE_PROGRESS = 'progress';
    public const TYPE_RESULT = 'result';
    public const TYPE_ERROR = 'error';
    public const TYPE_THINKING = 'thinking';

    // Relationships
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    // Static helpers for creating logs
    public static function info(Pipeline $pipeline, string $agentType, string $message, ?array $data = null): self
    {
        return self::createLog($pipeline, $agentType, self::TYPE_INFO, $message, $data);
    }

    public static function progress(Pipeline $pipeline, string $agentType, int $progress, ?string $message = null): self
    {
        return self::createLog($pipeline, $agentType, self::TYPE_PROGRESS, $message ?? "Progress: {$progress}%", [
            'progress' => $progress,
        ]);
    }

    public static function result(Pipeline $pipeline, string $agentType, string $message, array $result): self
    {
        return self::createLog($pipeline, $agentType, self::TYPE_RESULT, $message, $result);
    }

    public static function error(Pipeline $pipeline, string $agentType, string $message, ?array $data = null): self
    {
        return self::createLog($pipeline, $agentType, self::TYPE_ERROR, $message, $data);
    }

    public static function thinking(Pipeline $pipeline, string $agentType, string $thought): self
    {
        return self::createLog($pipeline, $agentType, self::TYPE_THINKING, $thought);
    }

    private static function createLog(
        Pipeline $pipeline,
        string $agentType,
        string $logType,
        string $message,
        ?array $data = null
    ): self {
        return self::create([
            'pipeline_id' => $pipeline->id,
            'agent_type' => $agentType,
            'log_type' => $logType,
            'message' => $message,
            'data' => $data,
        ]);
    }

    // Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('log_type', $type);
    }

    public function scopeForAgent($query, string $agentType)
    {
        return $query->where('agent_type', $agentType);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
