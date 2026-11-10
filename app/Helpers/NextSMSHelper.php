<?php

namespace App\Helpers;

use App\Support\NetworkDetector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NextSMSHelper
{
    public function sendSingleSMS(
        string $to,
        string $text,
        ?string $reference = null,
        ?string $from = null
    ): array {
        $payload = [
            'from' => $from ?: $this->defaultSender(),
            'to' => $this->normalizeMobileNumber($to),
            'text' => $text,
            'reference' => $reference ?: $this->makeReference(),
        ];

        return $this->request('POST', $this->endpoint('text/single'), $payload);
    }

    /**
     * Same text to many recipients.
     * POST /api/sms/v1/text/multi
     *
     * @param  array<int, string>  $recipients
     */
    public function sendMultipleSMS(
        array $recipients,
        string $text,
        ?string $reference = null,
        ?string $from = null
    ): array {
        $normalized = $this->normalizeRecipients($recipients);

        if ($normalized === []) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'At least one valid recipient is required.',
                'body' => null,
            ];
        }

        $payload = [
            'from' => $from ?: $this->defaultSender(),
            'to' => $normalized,
            'text' => $text,
            'reference' => $reference ?: $this->makeReference(),
        ];

        return $this->request('POST', $this->endpoint('text/multi'), $payload);
    }

    /**
     * Different texts / recipient groups in one request.
     * POST /api/sms/v1/text/multi with a messages[] payload.
     *
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function sendMultipleMessages(array $messages, ?string $batchReference = null): array
    {
        $formatted = [];

        foreach ($messages as $index => $message) {
            $to = $message['to'] ?? null;
            $text = $message['text'] ?? null;

            if ($to === null || $text === null || $text === '') {
                return [
                    'success' => false,
                    'status' => 422,
                    'error' => "Message at index {$index} requires 'to' and 'text'.",
                    'body' => null,
                ];
            }

            $recipients = is_array($to) ? $to : [$to];
            $normalized = $this->normalizeRecipients($recipients);

            if ($normalized === []) {
                return [
                    'success' => false,
                    'status' => 422,
                    'error' => "Message at index {$index} has no valid recipients.",
                    'body' => null,
                ];
            }

            $item = [
                'from' => $message['from'] ?? $this->defaultSender(),
                'to' => count($normalized) === 1 ? $normalized[0] : $normalized,
                'text' => $text,
            ];

            if (! empty($message['reference'])) {
                $item['reference'] = $message['reference'];
            } elseif ($batchReference) {
                $item['reference'] = $batchReference.'-'.($index + 1);
            }

            $formatted[] = $item;
        }

        if ($formatted === []) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'At least one message is required.',
                'body' => null,
            ];
        }

        $payload = ['messages' => $formatted];
        if ($batchReference) {
            $payload['reference'] = $batchReference;
        }

        return $this->request('POST', $this->endpoint('text/multi'), $payload);
    }

    public function getDeliveryReport(?string $messageId = null): array
    {
        $path = 'reports';
        if ($messageId) {
            $path .= '?messageId='.urlencode($messageId);
        }

        return $this->request('GET', $this->endpoint($path));
    }

    public function getBalance(): array
    {
        return $this->request('GET', $this->endpoint('balance'));
    }

    /**
     * Normalize to NextSMS format: 255XXXXXXXXX (no +).
     */
    public function normalizeMobileNumber(?string $mobileNumber): ?string
    {
        $national = NetworkDetector::normalizeNational($mobileNumber);

        if ($national === null) {
            return null;
        }

        $digits = '255'.substr($national, 1);

        return preg_match('/^255\d{9}$/', $digits) === 1 ? $digits : null;
    }

    /**
     * @param  array<int, string>  $recipients
     * @return array<int, string>
     */
    public function normalizeRecipients(array $recipients): array
    {
        $normalized = [];

        foreach ($recipients as $recipient) {
            $number = $this->normalizeMobileNumber((string) $recipient);
            if ($number !== null) {
                $normalized[] = $number;
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function request(string $method, string $url, ?array $payload = null): array
    {
        try {
            $pending = Http::withHeaders([
                'Authorization' => $this->authHeader(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30);

            $response = strtoupper($method) === 'GET'
                ? $pending->get($url)
                : $pending->send($method, $url, $payload ? ['json' => $payload] : []);

            $body = $response->json();
            if (! is_array($body)) {
                $body = ['raw' => $response->body()];
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'body' => $body,
                'request' => $payload,
            ];
        } catch (\Throwable $e) {
            Log::error('nextsms.request_failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 0,
                'error' => $e->getMessage(),
                'body' => null,
                'request' => $payload,
            ];
        }
    }

    protected function endpoint(string $path): string
    {
        $base = rtrim((string) config('services.nextsms.base_url', 'https://messaging-service.co.tz'), '/');
        $prefix = config('services.nextsms.test_mode', false)
            ? '/api/sms/v1/test'
            : '/api/sms/v1';

        return $base.$prefix.'/'.ltrim($path, '/');
    }

    protected function authHeader(): string
    {
        $username = (string) config('services.nextsms.username');
        $password = (string) config('services.nextsms.password');

        return 'Basic '.base64_encode("{$username}:{$password}");
    }

    protected function defaultSender(): string
    {
        return (string) config('services.nextsms.sender_id', 'NEXTSMS');
    }

    protected function makeReference(): string
    {
        return 'WF-'.Str::upper(Str::random(12));
    }
}
