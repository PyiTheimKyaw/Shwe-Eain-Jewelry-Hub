<?php

namespace App\Providers;

use App\Services\FirebaseInquiryService;
use App\Services\MediaStorageService;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseInquiryService::class, function ($app) {
            $mediaStorage = $app->make(MediaStorageService::class);
            $path = config('services.firebase.credentials');
            if (! is_string($path) || $path === '') {
                return new FirebaseInquiryService(null, $mediaStorage);
            }

            $fullPath = is_readable($path) ? $path : base_path($path);
            if (! is_readable($fullPath)) {
                return new FirebaseInquiryService(null, $mediaStorage);
            }

            $factory = (new Factory)->withServiceAccount($fullPath);

            return new FirebaseInquiryService($factory, $mediaStorage);
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
