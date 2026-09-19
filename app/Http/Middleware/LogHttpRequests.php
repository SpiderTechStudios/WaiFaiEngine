<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Global HTTP request tracer.
 *
 * Logs EVERY request that reaches Laravel (valid routes, API routes, WiFiDog
 * routes, auth routes, webhooks, and 404s) to a dedicated channel/file:
 *
 *     storage/logs/http-requests.log
 *
 * Enabled with HTTP_REQUEST_LOGGING=true (default on). Logging is best-effort:
 * it can never break or alter the response (except adding X-Request-ID).
 */
class LogHttpRequests
{
    /**
     * Substrings that mark an input key / header as sensitive (case-insensitive).
     *
     * @var list<string>
     */
    private const REDACT_PATTERNS = [
        'password',
        'secret',
        'token',
        'authorization',
        'cookie',
        'credential',
        'private_key',
        'api_key',
        'apikey',
        'signature',
        'otp',
        'cvv',
        'card_number',
        'card_no',
        'session',
    ];

    /**
     * @var list<string>
     */
    private const REDACT_HEADERS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-csrf-token',
        'x-xsrf-token',
        'x-api-key',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        $requestId = (string) ($request->headers->get('X-Request-ID') ?: Str::uuid());
        $request->attributes->set('request_id', $requestId);

        $startedAt = microtime(true);
        $status = 500;

        try {
            $response = $next($request);
            $status = $response->getStatusCode();
        } catch (Throwable $exception) {
            $this->write($request, $status, $startedAt, $requestId, $exception);

            throw $exception;
        }

        try {
            $response->headers->set('X-Request-ID', $requestId);
        } catch (Throwable) {
            // Never let logging/headers interfere with the response.
        }

        $this->write($request, $status, $startedAt, $requestId);

        return $response;
    }

    private function enabled(): bool
    {
        return (bool) config('logging.http_request_logging', true);
    }

    private function write(
        Request $request,
        int $status,
        float $startedAt,
        string $requestId,
        ?Throwable $exception = null,
    ): void {
        try {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $route = $request->route();
            $action = $route?->getActionName();

            $lines = [
                sprintf('%s %s', $request->method(), '/'.ltrim($request->path(), '/')),
                '  request_id : '.$requestId,
                '  ip         : '.($request->ip() ?? '-'),
                '  user_agent : '.($this->encode($request->userAgent() ?? '-')),
                '  route      : '.($route?->uri() ?? '(unmatched)'),
                '  route_name : '.($route?->getName() ?? '-'),
                '  action     : '.($action ?? '-'),
                '  status     : '.$status,
                '  duration   : '.$durationMs.'ms',
                '  query      : '.$this->encode($this->redact($request->query())),
                '  body       : '.$this->encode($this->redact($this->input($request))),
                '  headers    : '.$this->encode($this->redactHeaders($request)),
            ];

            if ($exception !== null) {
                $lines[] = '  exception  : '.get_class($exception).': '.$exception->getMessage();
            }

            Log::channel('http_requests')->info(implode(PHP_EOL, $lines));
        } catch (Throwable $e) {
            // Logging must never break the API request.
            try {
                Log::channel('single')->warning('http_request_logging_failed', [
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // give up silently
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        $input = $request->all();

        foreach ($input as $key => $value) {
            if ($value instanceof UploadedFile) {
                $input[$key] = '[file: '.$value->getClientOriginalName().']';
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data, int $depth = 0): array
    {
        if ($depth > 4) {
            return ['(depth)'];
        }

        $out = [];
        foreach ($data as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $out[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->redact($value, $depth + 1);
                continue;
            }

            if ($value instanceof UploadedFile) {
                $out[$key] = '[file: '.$value->getClientOriginalName().']';
                continue;
            }

            if (is_string($value) && strlen($value) > 512) {
                $out[$key] = substr($value, 0, 512).'…';
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function redactHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $lower = strtolower((string) $name);

            if (in_array($lower, self::REDACT_HEADERS, true) || $this->isSensitive($lower)) {
                $headers[$name] = '***';
                continue;
            }

            $headers[$name] = $values;
        }

        return $headers;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::REDACT_PATTERNS as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function encode(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return '[unencodable]';
        }

        return strlen($json) > 4000 ? substr($json, 0, 4000).'…[truncated]' : $json;
    }
}
