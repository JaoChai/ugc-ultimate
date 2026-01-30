<?php

namespace App\Policies;

use App\Models\AgentConfig;
use App\Models\User;

class AgentConfigPolicy
{
    public function view(User $user, AgentConfig $agentConfig): bool
    {
        return $user->id === $agentConfig->user_id;
    }

    public function update(User $user, AgentConfig $agentConfig): bool
    {
        return $user->id === $agentConfig->user_id;
    }

    public function delete(User $user, AgentConfig $agentConfig): bool
    {
        return $user->id === $agentConfig->user_id;
    }
}
