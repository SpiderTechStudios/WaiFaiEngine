<?php

namespace App\Http\Requests\Captive;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AuthorizeCaptiveSessionRequest extends FormRequest
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
            'access_grant_id' => ['nullable', 'integer'],
            'payment_transaction_id' => ['nullable', 'integer'],
            'mac_address' => ['nullable', 'string', 'max:32'],
            'ip_address' => ['nullable', 'ip'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('payment_transaction_id') && ! $this->filled('access_grant_id')) {
                $validator->errors()->add(
                    'access_grant_id',
                    'Provide access_grant_id or payment_transaction_id.',
                );
            }
        });
    }
}
