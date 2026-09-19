<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures every request that looks like WiFiDog traffic, no matter which path
 * or User-Agent it uses. This is how we find out whether the AP's
 * server-to-server auth/ping calls reach the API at all, and on which
 * path/scheme/port.
 *
 *   direction: ap->api     = looks like the gateway daemon (UA mentions ruijie/
 *                            reyee/wifidog/nas, or a gateway path was requested)
 *   direction: mobile->api = anything else (phone browser)
 *
 * Logged to storage/logs/wifidog.log as "wifidog.hit".
 */
class LogWiFiDogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = (string) $request->userAgent();
        $path = strtolower($request->path());

        // Gateway UA or a path a WiFiDog gateway might call.
        $isGateway = (bool) preg_match('/wifidog|ruijie|reyee|apfree|\bnas\b/i', $userAgent);
        $isGatewayPath = str_contains($path, 'wifidog')
            || (bool) preg_match('#^(auth|ping|portal|login)(/|$)#', $path);

        // Don't log our own JSON API or the portal page on every hit.
        $excluded = str_starts_with($path, 'api/v1')
            || str_starts_with($path, 'connect')
            || $path === 'up'
            || str_starts_with($path, '_ignition')
            || str_starts_with($path, 'telescope')
            || str_starts_with($path, 'storage')
            || str_starts_with($path, 'build');

        $looksLikeWiFiDog = ! $excluded && ($isGateway || $isGatewayPath);

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
