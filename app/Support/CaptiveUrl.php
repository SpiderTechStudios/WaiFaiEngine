<?php

namespace App\Support;

class CaptiveUrl
{
    /**
     * Hosts that must never be a post-auth redirect target, otherwise the
     * browser loops back into the captive portal or the WiFiDog API.
     *
     * @return list<string>
     */
    public static function selfHosts(): array
    {
        $urls = [
            config('captive.portal_url'),
            config('captive.portal_origin'),
            config('captive.portal_base_url'),
            config('app.url'),
        ];

        $hosts = [];
        foreach ($urls as $url) {
            $host = parse_url((string) $url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * True when the URL is an absolute http(s) destination that is not the
     * captive portal, the API, or a WiFiDog endpoint.
     */
    public static function isExternalRedirect(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        if (str_contains((string) ($parts['path'] ?? ''), '/wifidog')) {
            return false;
        }

        return ! in_array(strtolower((string) $parts['host']), self::selfHosts(), true);
    }

    /**
     * Absolute fallback used when there is no usable original URL.
     */
    public static function successRedirectUrl(): string
    {
        $fallback = (string) config('captive.portal_success_url', 'http://www.google.com');

        return self::isExternalRedirect($fallback) ? $fallback : 'http://www.google.com';
    }
}
