<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

class PlatformPaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = config('platform.payment_webhook_secret');

        if (! filled($secret)) {
            // Allow unsigned callbacks only when auto-paid/dev mode is enabled.
            return (bool) config('platform.payment_auto_paid');
        }

        return hash_equals((string) $secret, (string) $this->header('X-Platform-Payment-Secret'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required_without:transaction_reference', 'string', 'max:100'],
            'transaction_reference' => ['required_without:reference', 'string', 'max:100'],
            'status' => ['required', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'failure_reason' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:255'],
        ];
    }
}
