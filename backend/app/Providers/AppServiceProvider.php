<?php

namespace App\Providers;

use App\Models\AgentConfig;
use App\Models\ApiKey;
use App\Models\Channel;
use App\Models\Pipeline;
use App\Models\Project;
use App\Policies\AgentConfigPolicy;
use App\Policies\ApiKeyPolicy;
use App\Policies\ChannelPolicy;
use App\Policies\PipelinePolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(ApiKey::class, ApiKeyPolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Pipeline::class, PipelinePolicy::class);
        Gate::policy(AgentConfig::class, AgentConfigPolicy::class);
    }
}
