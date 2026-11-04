<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
        // Supports MySQL installations configured with a 1000-byte index limit.
        Schema::defaultStringLength(191);
        Gate::policy(\App\Models\Investigation::class, \App\Policies\InvestigationPolicy::class);
        RateLimiter::for('api', fn () => Limit::perMinute(60)->by(request()->ip()));
    }
}
