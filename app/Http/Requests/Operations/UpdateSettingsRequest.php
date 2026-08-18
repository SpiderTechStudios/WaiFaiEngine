<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'portal_subdomain' => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'voucher_code_digits' => ['nullable', 'integer', 'min:4', 'max:12'],
            'payout_methods' => ['nullable', 'array'],
            'payout_methods.*.provider' => ['required_with:payout_methods', 'string', 'max:50'],
            'payout_methods.*.phone' => ['nullable', 'string', 'max:50'],
            'payout_methods.*.name' => ['nullable', 'string', 'max:255'],
            'captive_portal_welcome_message' => ['nullable', 'string'],
            'ruijie_account_id' => ['nullable', 'string', 'max:255'],
            'ruijie_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
