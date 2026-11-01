<?php

namespace App\Providers;

use App\Support\CompanyContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $portalClient = function (Request $request): string {
            $payment = $request->route('payment');
            if (is_object($payment)) {
                $payment = method_exists($payment, 'getRouteKey') ? $payment->getRouteKey() : ($payment->id ?? '');
            }

            $parts = array_filter([
                (string) $request->input('captive_session', ''),
                (string) $request->input('customer_phone', ''),
                (string) $request->input('mac_address', ''),
                (string) $payment,
            ], fn ($value) => $value !== '');

            return $parts === [] ? $request->ip() : implode('|', $parts);
        };

        RateLimiter::for('portal-payment-status', function (Request $request) use ($portalClient) {
            return Limit::perMinute(120)->by('pay-status|'.$request->ip().'|'.$portalClient($request));
        });

        RateLimiter::for('portal-payment-create', function (Request $request) use ($portalClient) {
            return [
                Limit::perMinute(6)->by('pay-create|'.$portalClient($request)),
                Limit::perMinute(120)->by('pay-create-ip|'.$request->ip()),
            ];
        });

        // Authentication is keyed by account + IP (not IP alone): several staff
        // or customers often share one office/hotspot public address, and a
        // single shared bucket made them hit "Too Many Attempts" together.
        // A generous per-IP ceiling still protects against brute force.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(10)->by('login|'.$email.'|'.$request->ip()),
                Limit::perMinute(120)->by('login-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('forgot|'.$email.'|'.$request->ip()),
                Limit::perMinute(60)->by('forgot-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('reset-password', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(10)->by('reset|'.$email.'|'.$request->ip()),
                Limit::perMinute(60)->by('reset-ip|'.$request->ip()),
            ];
        });

        // Portal actions happen behind a shared hotspot/NAT address, so key
        // them by the customer's captive session/phone/MAC when available and
        // keep a much higher per-IP ceiling for the whole hotspot.
        RateLimiter::for('portal-voucher', function (Request $request) use ($portalClient) {
            return [
                Limit::perMinute(10)->by('voucher|'.$portalClient($request)),
                Limit::perMinute(120)->by('voucher-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('portal-offer', function (Request $request) use ($portalClient) {
            return [
                Limit::perMinute(10)->by('offer|'.$portalClient($request)),
                Limit::perMinute(120)->by('offer-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('portal-session', function (Request $request) use ($portalClient) {
            return [
                Limit::perMinute(20)->by('session|'.$portalClient($request)),
                Limit::perMinute(240)->by('session-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('portal-restore', function (Request $request) use ($portalClient) {
            return [
                Limit::perMinute(10)->by('restore|'.$portalClient($request)),
                Limit::perMinute(120)->by('restore-ip|'.$request->ip()),
            ];
        });
    }
}
