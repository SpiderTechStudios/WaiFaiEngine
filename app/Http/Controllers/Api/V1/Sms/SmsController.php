<?php

namespace App\Http\Controllers\Api\V1\Sms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\BroadcastSmsRequest;
use App\Http\Requests\Sms\SendMultipleSmsRequest;
use App\Http\Requests\Sms\SendSingleSmsRequest;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    private const BROADCAST_CHUNK_SIZE = 100;

    private const BROADCAST_MAX_RECIPIENTS = 2000;

    public function __construct(private SmsService $smsService) {}

    public function sendSingle(SendSingleSmsRequest $request): JsonResponse
    {
        $result = $this->smsService->sendSingle(
            $request->user(),
            $request->validated('to'),
            $request->validated('text'),
            $request->validated('reference'),
            $request->validated('from'),
        );

        $success = (bool) ($result['provider']['success'] ?? false);

        return $success
            ? $this->success($result, 'SMS submitted to NextSMS.')
            : $this->error(
                $result['provider'],
                $result['provider']['error'] ?? 'Failed to send SMS via NextSMS.',
                (int) ($result['provider']['status'] ?: 502),
            );
    }

    public function sendMultiple(SendMultipleSmsRequest $request): JsonResponse
    {
        $result = $this->smsService->sendMultiple(
            $request->user(),
            $request->validated('to'),
            $request->validated('text'),
            $request->validated('reference'),
            $request->validated('from'),
        );

        $success = (bool) ($result['provider']['success'] ?? false);

        return $success
            ? $this->success($result, 'Bulk SMS submitted to NextSMS.')
            : $this->error(
                $result['provider'],
                $result['provider']['error'] ?? 'Failed to send bulk SMS via NextSMS.',
                (int) ($result['provider']['status'] ?: 502),
            );
    }

    public function broadcast(BroadcastSmsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $text = (string) $data['text'];
        $from = $data['from'] ?? null;
        $reference = $data['reference'] ?? null;

        $recipients = $data['recipients'] ?? [];

        if ($recipients === []) {
            $query = User::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '');

            if (($data['audience'] ?? 'active') === 'active') {
                $query->where('status', 'active');
            }

            if (! empty($data['company_id'])) {
                $query->whereHas('memberships', fn ($membership) => $membership->where('company_id', $data['company_id']));
            }

            $recipients = $query->pluck('phone')->all();
        }

        $normalized = $this->smsService->normalizeRecipients($recipients);

        if ($normalized === []) {
            return $this->error(['recipients' => ['No valid mobile numbers found.']], 'No valid recipients.', 422);
        }

        if (count($normalized) > self::BROADCAST_MAX_RECIPIENTS) {
            return $this->error(
                ['recipients' => ['A broadcast is limited to '.self::BROADCAST_MAX_RECIPIENTS.' recipients per request.']],
                'Too many recipients.',
                422,
            );
        }

        $batches = [];
        $messages = [];

        foreach (array_chunk($normalized, self::BROADCAST_CHUNK_SIZE) as $chunk) {
            $result = $this->smsService->sendMultiple(
                $request->user(),
                $chunk,
                $text,
                $reference,
                $from,
            );

            $messages = [...$messages, ...$result['messages']];

            $batches[] = [
                'recipients' => count($chunk),
                'success' => (bool) ($result['provider']['success'] ?? false),
                'status' => $result['provider']['status'] ?? null,
                'error' => $result['provider']['error'] ?? null,
            ];
        }

        $payload = [
            'recipients_count' => count($normalized),
            'batches' => $batches,
            'messages' => $messages,
        ];

        $allSucceeded = collect($batches)->every(fn (array $batch) => $batch['success']);

        return $allSucceeded
            ? $this->success($payload, 'Broadcast SMS submitted to NextSMS.')
            : $this->error($payload, 'Some SMS batches failed.', 502);
    }

    public function deliveryReport(Request $request): JsonResponse
    {
        $secret = config('services.nextsms.callback_secret');

        if ($secret) {
            $provided = $request->header('X-NextSMS-Secret')
                ?? $request->query('secret')
                ?? $request->input('secret');

            if (! hash_equals((string) $secret, (string) $provided)) {
                return $this->error([], 'Invalid callback secret.', 403);
            }
        }

        $payload = $request->isMethod('GET') ? [] : $request->all();

        $saved = $this->smsService->handleDeliveryReport($payload, $request->query());

        return $this->success([
            'received' => count($saved),
            'reports' => $saved,
        ], 'Delivery report processed.');
    }

    public function balance(): JsonResponse
    {
        $result = $this->smsService->getBalance();

        return ($result['success'] ?? false)
            ? $this->success($result, 'NextSMS balance retrieved.')
            : $this->error($result, $result['error'] ?? 'Unable to retrieve NextSMS balance.', 502);
    }
}
