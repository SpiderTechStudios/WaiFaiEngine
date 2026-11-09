<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $to, string $message): bool
    {
        $driver = (string) config('services.sms.driver', 'log');

        if ($driver === 'log') {
            Log::info('sms.send', [
                'to' => $to,
                'message' => $message,
            ]);

            return true;
        }

        if ($driver !== 'http') {
            return false;
        }

        $url = (string) config('services.sms.url', '');

        if ($url === '') {
            return false;
        }

        try {
            $response = Http::withToken((string) config('services.sms.token', ''))
                ->acceptJson()
                ->timeout(15)
                ->post($url, [
                    'to' => $to,
                    'message' => $message,
                    'sender' => config('services.sms.sender'),
                ]);

            if (! $response->successful()) {
                Log::warning('sms.send_failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('sms.send_error', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
