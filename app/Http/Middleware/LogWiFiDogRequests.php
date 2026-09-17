<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures every request that looks like WiFiDog traffic, no matter which path
 * it uses. This is how we find out whether the AP's server-to-server auth/ping
 * calls reach the API at all, and on which path/scheme/port.
 *
 *   direction: ap->api  = User-Agent contains "WiFiDog" (the AP daemon)
 *   direction: mobile->api = anything else (phone browser)
 *
 * Logged to storage/logs/wifidog.log as "wifidog.hit".
 */
class LogWiFiDogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = (string) $request->userAgent();
        $isGateway = str_contains($userAgent, 'WiFiDog');
        $looksLikeWiFiDog = $isGateway || str_contains($request->path(), 'wifidog');

        $response = $next($request);

        if ($looksLikeWiFiDog) {
            $query = $request->query();
            foreach (['token', 'password'] as $secret) {
                if (array_key_exists($secret, $query)) {
                    $query[$secret] = '***';
                }
            }

            Log::channel('wifidog')->info('wifidog.hit', [
                'direction' => $isGateway ? 'ap->api' : 'mobile->api',
                'caller' => $isGateway ? 'ap' : 'mobile',
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'query' => $query,
                'remote_ip' => $request->ip(),
                'host' => $request->getHost(),
                'scheme' => $request->getScheme(),
                'is_secure' => $request->isSecure(),
                'matched_route' => optional($request->route())->uri(),
                'response_status' => $response->getStatusCode(),
                'user_agent' => $userAgent,
            ]);
        }

        return $response;
    }
}
