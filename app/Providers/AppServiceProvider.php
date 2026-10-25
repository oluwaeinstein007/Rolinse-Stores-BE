<?php

namespace App\Providers;

use App\Services\GeneralService;
use App\Services\NotificationService;
use App\Services\ActivityLogger;
use App\Services\StripeService;
use App\Services\DeliveryService;
use Cloudinary\Cloudinary;

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

        $this->app->bind(DeliveryService::class, function ($app) {
            return new DeliveryService();
        });

        // Official Cloudinary SDK, built from CLOUDINARY_URL (config/cloudinary.php).
        // Resolved lazily at the call sites, so a missing CLOUDINARY_URL only
        // fails when an upload or delete is actually attempted.
        $this->app->singleton(Cloudinary::class, function ($app) {
            return new Cloudinary($app['config']->get('cloudinary.cloud_url'));
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
