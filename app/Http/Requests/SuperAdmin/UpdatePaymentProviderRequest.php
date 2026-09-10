<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentProviderRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
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
