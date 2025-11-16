<?php

namespace App\Providers;

use App\Services\ScheduleOptimizationService;
use Illuminate\Support\ServiceProvider;

class OptimizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ScheduleOptimizationService::class, function ($app) {
            return new ScheduleOptimizationService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}