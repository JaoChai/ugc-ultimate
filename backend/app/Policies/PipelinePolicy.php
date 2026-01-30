<?php

namespace App\Policies;

use App\Models\Pipeline;
use App\Models\User;

class PipelinePolicy
{
    public function view(User $user, Pipeline $pipeline): bool
    {
        return $user->id === $pipeline->user_id;
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return $user->id === $pipeline->user_id;
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        return $user->id === $pipeline->user_id;
    }
}
