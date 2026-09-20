<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PortalStoreSessionRequest extends FormRequest
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
            'payment_transaction_id' => ['nullable', 'integer'],
            'access_grant_id' => ['nullable', 'integer'],
            'mac_address' => ['required', 'string', 'max:32'],
            'ip_address' => ['nullable', 'ip'],
            'router_id' => ['nullable', 'integer'],
            'network_device_id' => ['nullable', 'integer'],
            'network_station_id' => ['nullable', 'integer'],
            'network_ssid_id' => ['nullable', 'integer'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'captive_session' => ['nullable', 'string', 'size:32'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('payment_transaction_id') && ! $this->filled('access_grant_id')) {
                $validator->errors()->add(
                    'payment_transaction_id',
                    'Provide payment_transaction_id or access_grant_id.',
                );
            }
        });
    }
}
