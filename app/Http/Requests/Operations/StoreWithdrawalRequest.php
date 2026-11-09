<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
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
            'destination_phone' => ['required_without:payout_account_id', 'nullable', 'string', 'max:50'],
            'destination_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'provider' => ['nullable', 'string', 'max:50'],
            'payment_provider_slug' => ['nullable', 'string', 'max:50', 'exists:payment_providers,slug'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
