<?php

use App\Models\Pipeline;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Pipeline channel - only owner can listen to pipeline events
Broadcast::channel('pipeline.{pipelineId}', function ($user, $pipelineId) {
    $pipeline = Pipeline::find($pipelineId);
    return $pipeline && $pipeline->user_id === $user->id;
});
