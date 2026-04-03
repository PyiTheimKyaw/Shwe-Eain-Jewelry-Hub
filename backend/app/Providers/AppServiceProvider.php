<?php

namespace App\Providers;

use App\Services\FirebaseInquiryService;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseInquiryService::class, function () {
            $path = config('services.firebase.credentials');
            if (! is_string($path) || $path === '') {
                return new FirebaseInquiryService(null);
            }

            $fullPath = is_readable($path) ? $path : base_path($path);
            if (! is_readable($fullPath)) {
                return new FirebaseInquiryService(null);
            }

            $factory = (new Factory)->withServiceAccount($fullPath);

            return new FirebaseInquiryService($factory);
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
