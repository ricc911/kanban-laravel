<?php

namespace App\Providers;

use App\Models\TaskComment;
use App\Policies\TaskCommentPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(TaskComment::class, TaskCommentPolicy::class);
        RateLimiter::for('ai-generation', fn (Request $request): Limit => Limit::perMinute(10)->by((string) ($request->user()?->id ?? $request->ip())));
    }
}
