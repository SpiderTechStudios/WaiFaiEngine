<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class PreviewWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'payout_account_id' => ['nullable', 'integer', 'min:1'],
            'destination_phone' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'provider' => ['nullable', 'string', 'max:50'],
            'payment_provider_slug' => ['nullable', 'string', 'max:50', 'exists:payment_providers,slug'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
