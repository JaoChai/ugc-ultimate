<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

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
        Route::post('/{project}/generate-videos', [ProjectController::class, 'generateVideos']);
        Route::post('/{project}/generate-all', [ProjectController::class, 'generateAll']);
        Route::post('/{project}/compose', [ProjectController::class, 'compose']);
        Route::post('/{project}/recompose', [ProjectController::class, 'recompose']);
        Route::get('/{project}/status', [ProjectController::class, 'status']);
        Route::get('/{project}/assets', [ProjectController::class, 'assets']);
        Route::get('/{project}/download', [ProjectController::class, 'download']);
    });
});
