<?php

use App\Http\Controllers\AgentConfigController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Health check routes (public)
Route::prefix('health')->group(function () {
    Route::get('/', [HealthController::class, 'index']);
    Route::get('/queue', [HealthController::class, 'queue']);
    Route::get('/database', [HealthController::class, 'database']);
});

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Webhooks (public, no auth required)
Route::prefix('webhooks')->group(function () {
    Route::post('/kie', [WebhookController::class, 'handleKie']);
    Route::post('/r2', [WebhookController::class, 'handleR2']);
});

// Public agent config routes (default prompts are not user-specific)
Route::get('/agent-configs/defaults/{agentType}', [AgentConfigController::class, 'getDefaultPrompt']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });

    // API Keys
    Route::prefix('api-keys')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index']);
        Route::post('/', [ApiKeyController::class, 'store']);
        Route::put('/{apiKey}', [ApiKeyController::class, 'update']);
        Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy']);
        Route::post('/{apiKey}/test', [ApiKeyController::class, 'test']);
    });

    // Channels
    Route::prefix('channels')->group(function () {
        Route::get('/', [ChannelController::class, 'index']);
        Route::post('/', [ChannelController::class, 'store']);
        Route::get('/{channel}', [ChannelController::class, 'show']);
        Route::put('/{channel}', [ChannelController::class, 'update']);
        Route::delete('/{channel}', [ChannelController::class, 'destroy']);
        Route::get('/{channel}/stats', [ChannelController::class, 'stats']);
        Route::put('/{channel}/schedule', [ChannelController::class, 'updateSchedule']);
    });

    // Projects
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/{project}', [ProjectController::class, 'show']);
        Route::put('/{project}', [ProjectController::class, 'update']);
        Route::delete('/{project}', [ProjectController::class, 'destroy']);
        Route::post('/{project}/generate-concept', [ProjectController::class, 'generateConcept']);
        Route::post('/{project}/generate-music', [ProjectController::class, 'generateMusic']);
        Route::post('/{project}/generate-images', [ProjectController::class, 'generateImages']);
        Route::post('/{project}/generate-all', [ProjectController::class, 'generateAll']);
        Route::get('/{project}/status', [ProjectController::class, 'status']);
        Route::get('/{project}/assets', [ProjectController::class, 'assets']);
        Route::get('/{project}/download', [ProjectController::class, 'download']);
    });

    // Pipelines
    Route::prefix('pipelines')->group(function () {
        Route::get('/', [PipelineController::class, 'index']);
        Route::post('/project/{project}', [PipelineController::class, 'store']);
        Route::get('/{pipeline}', [PipelineController::class, 'show']);
        Route::post('/{pipeline}/start', [PipelineController::class, 'start']);
        Route::post('/{pipeline}/pause', [PipelineController::class, 'pause']);
        Route::post('/{pipeline}/resume', [PipelineController::class, 'resume']);
        Route::post('/{pipeline}/cancel', [PipelineController::class, 'cancel']);
        Route::post('/{pipeline}/step', [PipelineController::class, 'runStep']);
        Route::get('/{pipeline}/logs', [PipelineController::class, 'logs']);
        Route::get('/{pipeline}/step/{step}', [PipelineController::class, 'stepResult']);
    });

    // Agent Configs (protected - user-specific configs)
    Route::prefix('agent-configs')->group(function () {
        Route::get('/', [AgentConfigController::class, 'index']);
        Route::post('/', [AgentConfigController::class, 'store']);
        Route::get('/{agentConfig}', [AgentConfigController::class, 'show']);
        Route::put('/{agentConfig}', [AgentConfigController::class, 'update']);
        Route::delete('/{agentConfig}', [AgentConfigController::class, 'destroy']);
        Route::post('/{agentConfig}/set-default', [AgentConfigController::class, 'setDefault']);
        Route::post('/{agentConfig}/reset', [AgentConfigController::class, 'resetToDefault']);
        Route::post('/{agentConfig}/test', [AgentConfigController::class, 'test']);
    });
});
