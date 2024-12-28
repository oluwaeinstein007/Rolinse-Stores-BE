<?php

namespace App\Providers;

use App\Services\GeneralService;
use App\Services\NotificationService;
use App\Services\ActivityLogger;
use App\Services\StripeService;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind(GeneralService::class, function ($app) {
            return new GeneralService();
        });

        $this->app->bind(NotificationService::class, function ($app) {
            return new NotificationService();
        });

        $this->app->bind(ActivityLogger::class, function ($app) {
            return new ActivityLogger();
        });

        $this->app->bind(StripeService::class, function ($app) {
            return new StripeService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
