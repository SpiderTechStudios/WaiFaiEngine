<?php

namespace App\Services;

use App\Helpers\NextSMSHelper;
use App\Models\SmsDeliveryReport;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsService
{
    public function __construct(private NextSMSHelper $nextSms) {}

    public function sendSingle(User $actor, string $to, string $text, ?string $reference = null, ?string $from = null): array
    {
        $result = $this->nextSms->sendSingleSMS($to, $text, $reference, $from);
        $messages = $this->persistSendResult($actor, $result, $text, $from);

        return [
            'provider' => $result,
            'messages' => $messages,
        ];
    }

    /**
     * @param  array<int, string>  $recipients
     */
    public function sendMultiple(User $actor, array $recipients, string $text, ?string $reference = null, ?string $from = null): array
    {
        $result = $this->nextSms->sendMultipleSMS($recipients, $text, $reference, $from);
        $messages = $this->persistSendResult($actor, $result, $text, $from);

        return [
            'provider' => $result,
            'messages' => $messages,
        ];
    }

    /**
     * Each recipient gets its own rendered text.
     *
     * @param  array<int, array{to: string, text: string, reference?: string|null, from?: string|null}>  $messages
     */
    public function sendPersonalized(User $actor, array $messages, ?string $reference = null): array
    {
        $result = $this->nextSms->sendMultipleMessages($messages, $reference);
        $persisted = $this->persistSendResult($actor, $result, '', null);

        return [
            'provider' => $result,
            'messages' => $persisted,
        ];
    }

    /**
     * Send a transactional OTP SMS.
     */
    public function sendOtp(User $actor, string $to, string $text): bool
    {
        $result = $this->sendSingle(
            $actor,
            $to,
            $text,
            'OTP-'.Str::upper(Str::random(10)),
        );

        return (bool) ($result['provider']['success'] ?? false);
    }

    /**
     * Backward-compatible helper for callers that only need a boolean.
     */
    public function send(string $to, string $text, ?User $actor = null): bool
    {
        if (! $actor) {
            return (bool) ($this->nextSms->sendSingleSMS($to, $text)['success'] ?? false);
        }

        return $this->sendOtp($actor, $to, $text);
    }

    /**
     * @param  array<int, string>  $recipients
     * @return array<int, string>
     */
    public function normalizeRecipients(array $recipients): array
    {
        return $this->nextSms->normalizeRecipients($recipients);
    }

    public function getBalance(): array
    {
        return $this->nextSms->getBalance();
    }

    /**
     * @param  array<int, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    public function handleDeliveryReport(array $payload, array $query = []): array
    {
        $reports = $this->extractReportItems($payload, $query);
        $saved = [];

        foreach ($reports as $report) {
            $saved[] = $this->storeDeliveryReport($report, array_merge($payload, $query));
        }

        if ($saved === []) {
            Log::warning('nextsms.delivery_report.empty', [
                'payload' => $payload,
                'query' => $query,
            ]);
        }

        return $saved;
    }

    /**
     * @return array<int, SmsMessage>
     */
    private function persistSendResult(?User $actor, array $result, string $fallbackText, ?string $fallbackFrom): array
    {
        $request = $result['request'] ?? [];
        $body = $result['body'] ?? [];
        $providerMessages = Arr::get($body, 'messages', []);

        if (! is_array($providerMessages) || $providerMessages === []) {
            $to = $request['to'] ?? null;
            $recipients = is_array($to) ? $to : array_filter([$to]);
            $messages = [];

            foreach ($recipients as $recipient) {
                $messages[] = SmsMessage::query()->create([
                    'provider' => 'nextsms',
                    'provider_message_id' => null,
                    'reference' => $request['reference'] ?? null,
                    'sender' => $request['from'] ?? $fallbackFrom,
                    'recipient' => (string) $recipient,
                    'text' => $request['text'] ?? $fallbackText,
                    'status' => ($result['success'] ?? false) ? 'pending' : 'failed',
                    'http_status' => $result['status'] ?? null,
                    'request_payload' => $request,
                    'response_payload' => $body,
                    'sent_at' => ($result['success'] ?? false) ? now() : null,
                    'created_by' => $actor?->id,
                ]);
            }

            return $messages;
        }

        $saved = [];
        foreach ($providerMessages as $item) {
            $statusName = Arr::get($item, 'status.name')
                ?? Arr::get($item, 'status.groupName')
                ?? (($result['success'] ?? false) ? 'PENDING' : 'FAILED');

            $saved[] = SmsMessage::query()->create([
                'provider' => 'nextsms',
                'provider_message_id' => Arr::get($item, 'messageId') ?? Arr::get($item, 'message_id'),
                'reference' => Arr::get($item, 'reference') ?? ($request['reference'] ?? null),
                'sender' => Arr::get($item, 'from') ?? ($request['from'] ?? $fallbackFrom),
                'recipient' => (string) (Arr::get($item, 'to') ?? ''),
                'text' => Arr::get($item, 'text') ?? ($request['text'] ?? $fallbackText),
                'status' => $this->mapProviderStatus($statusName),
                'status_group' => Arr::get($item, 'status.groupName'),
                'http_status' => $result['status'] ?? null,
                'request_payload' => $request,
                'response_payload' => $item,
                'sent_at' => now(),
                'created_by' => $actor?->id,
            ]);
        }

        return $saved;
    }

    private function storeDeliveryReport(array $report, array $raw): SmsDeliveryReport
    {
        return DB::transaction(function () use ($report, $raw) {
            $messageId = $report['provider_message_id'] ?? null;
            $reference = $report['reference'] ?? null;
            $recipient = $report['recipient'] ?? null;
            $status = $this->mapProviderStatus($report['status'] ?? 'unknown');

            $message = null;
            if ($messageId) {
                $message = SmsMessage::query()->where('provider_message_id', $messageId)->first();
            }
            if (! $message && $reference && $recipient) {
                $message = SmsMessage::query()
                    ->where('reference', $reference)
                    ->where('recipient', $recipient)
                    ->latest('id')
                    ->first();
            }
            if (! $message && $recipient) {
                $message = SmsMessage::query()
                    ->where('recipient', $recipient)
                    ->whereIn('status', ['queued', 'pending', 'sent'])
                    ->latest('id')
                    ->first();
            }

            $delivery = SmsDeliveryReport::query()->create([
                'sms_message_id' => $message?->id,
                'provider_message_id' => $messageId,
                'reference' => $reference,
                'recipient' => $recipient,
                'status' => $status,
                'status_group' => $report['status_group'] ?? null,
                'error_name' => $report['error_name'] ?? null,
                'error_description' => $report['error_description'] ?? null,
                'done_at' => $this->parseTimestamp($report['done_at'] ?? null),
                'sent_at' => $this->parseTimestamp($report['sent_at'] ?? null),
                'raw_payload' => $raw,
            ]);

            if ($message) {
                $updates = [
                    'status' => $status,
                    'status_group' => $report['status_group'] ?? $message->status_group,
                ];

                if (! empty($messageId) && ! $message->provider_message_id) {
                    $updates['provider_message_id'] = $messageId;
                }

                if (in_array($status, ['delivered'], true)) {
                    $updates['delivered_at'] = $delivery->done_at ?? now();
                }

                $message->update($updates);
            }

            return $delivery;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractReportItems(array $payload, array $query): array
    {
        $source = $payload !== [] ? $payload : $query;

        if (isset($source['results']) && is_array($source['results'])) {
            return array_map(fn ($item) => $this->normalizeReportItem(is_array($item) ? $item : []), $source['results']);
        }

        if (isset($source['messages']) && is_array($source['messages'])) {
            return array_map(fn ($item) => $this->normalizeReportItem(is_array($item) ? $item : []), $source['messages']);
        }

        if ($this->looksLikeSingleReport($source)) {
            return [$this->normalizeReportItem($source)];
        }

        return [];
    }

    private function looksLikeSingleReport(array $source): bool
    {
        return isset($source['messageId'])
            || isset($source['message_id'])
            || isset($source['status'])
            || isset($source['to']);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeReportItem(array $item): array
    {
        $status = Arr::get($item, 'status.name')
            ?? Arr::get($item, 'status.groupName')
            ?? Arr::get($item, 'status')
            ?? Arr::get($item, 'messageStatus')
            ?? 'unknown';

        if (is_array($status)) {
            $status = $status['name'] ?? $status['groupName'] ?? 'unknown';
        }

        return [
            'provider_message_id' => Arr::get($item, 'messageId')
                ?? Arr::get($item, 'message_id')
                ?? Arr::get($item, 'msgid'),
            'reference' => Arr::get($item, 'reference') ?? Arr::get($item, 'bulkId'),
            'recipient' => Arr::get($item, 'to') ?? Arr::get($item, 'recipient') ?? Arr::get($item, 'phone'),
            'status' => (string) $status,
            'status_group' => Arr::get($item, 'status.groupName') ?? Arr::get($item, 'status_group'),
            'error_name' => Arr::get($item, 'error.name') ?? Arr::get($item, 'errorName'),
            'error_description' => Arr::get($item, 'error.description') ?? Arr::get($item, 'errorDescription'),
            'done_at' => Arr::get($item, 'doneAt') ?? Arr::get($item, 'done_at'),
            'sent_at' => Arr::get($item, 'sentAt') ?? Arr::get($item, 'sent_at'),
        ];
    }

    private function mapProviderStatus(string $status): string
    {
        $normalized = strtoupper(trim($status));

        return match (true) {
            str_contains($normalized, 'DELIVERED') => 'delivered',
            str_contains($normalized, 'REJECT') => 'rejected',
            str_contains($normalized, 'EXPIRED') => 'expired',
            str_contains($normalized, 'UNDELIVERABLE'),
            str_contains($normalized, 'FAILED'),
            str_contains($normalized, 'ERROR') => 'failed',
            str_contains($normalized, 'PENDING'),
            str_contains($normalized, 'ENROUTE'),
            str_contains($normalized, 'ACCEPTED') => 'pending',
            str_contains($normalized, 'SENT') => 'sent',
            default => 'unknown',
        };
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
