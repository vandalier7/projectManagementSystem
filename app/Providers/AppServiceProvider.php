<?php

namespace App\Providers;

use App\Services\TaskLifecycle\TaskLifecycleEngine;
use App\Services\TaskLifecycle\TaskRevisionChain;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaskLifecycleEngine::class);
        $this->app->singleton(TaskRevisionChain::class);
    }

    public function boot(): void
    {
        // The frontend expects plain arrays/objects, not { data: ... } envelopes.
        JsonResource::withoutWrapping();
    }
}
