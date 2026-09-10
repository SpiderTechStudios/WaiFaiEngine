<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentProviderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', 'unique:payment_providers,slug'],
            'description' => ['nullable', 'string'],
            'supports_payments' => ['nullable', 'boolean'],
            'supports_payouts' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_default_for_payments' => ['nullable', 'boolean'],
            'is_default_for_payouts' => ['nullable', 'boolean'],
            'credentials' => ['nullable', 'array'],
            'credentials.public_key' => ['nullable', 'string'],
            'credentials.secret_key' => ['nullable', 'string'],
            'credentials.encryption_key' => ['nullable', 'string'],
            'credentials.webhook_secret' => ['nullable', 'string'],
            'credentials.api_base_url' => ['nullable', 'url'],
            'credentials.app_id' => ['nullable', 'string'],
            'credentials.private_key' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
