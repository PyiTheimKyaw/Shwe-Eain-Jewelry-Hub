<?php

namespace App\Providers;

use App\Services\FirebaseInquiryService;
use App\Services\MediaStorageService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('auth-login', function (Request $request): array {
            $email = strtolower((string) $request->input('email', ''));
            $ip = (string) $request->ip();

            return [Limit::perMinute(5)->by($email.'|'.$ip)];
        });

        RateLimiter::for('auth-register', function (Request $request): array {
            return [Limit::perMinutes(10, 3)->by((string) $request->ip())];
        });

        RateLimiter::for('uploads', function (Request $request): array {
            $id = (string) ($request->user()?->id ?? 'guest');

            return [Limit::perMinute(20)->by('uploads|'.$id)];
        });

        RateLimiter::for('payment-proof-upload', function (Request $request): array {
            $id = (string) ($request->user()?->id ?? 'guest');
            $orderId = (string) $request->route('order');

            return [Limit::perMinutes(10, 6)->by('proof|'.$id.'|'.$orderId)];
        });

        RateLimiter::for('payment-admin-actions', function (Request $request): array {
            $id = (string) ($request->user()?->id ?? 'guest');

            return [Limit::perMinute(30)->by('payment-admin|'.$id)];
        });

        RateLimiter::for('quote-customer-decision', function (Request $request): array {
            $id = (string) ($request->user()?->id ?? 'guest');
            $quoteId = (string) $request->route('quote');

            return [Limit::perMinute(10)->by('quote-customer|'.$id.'|'.$quoteId)];
        });

        RateLimiter::for('quote-admin-mutations', function (Request $request): array {
            $id = (string) ($request->user()?->id ?? 'guest');

            return [Limit::perMinute(30)->by('quote-admin|'.$id)];
        });
    }
}
