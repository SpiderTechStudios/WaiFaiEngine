<?php

namespace App\Providers;

use App\Support\CompanyContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompanyContext::class, fn () => new CompanyContext);
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // Captive-portal clients often share one public IP behind the AP/NAT.
        // Status polling must not share a tiny per-IP bucket across all buyers.
        RateLimiter::for('portal-payment-status', function (Request $request) {
            $paymentKey = (string) ($request->route('payment') ?? 'unknown');

            return Limit::perMinute(90)->by($request->ip().'|'.$paymentKey);
        });

        RateLimiter::for('portal-payment-create', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
