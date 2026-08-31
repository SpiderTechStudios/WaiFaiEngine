<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RetryEnrollmentPaymentRequest extends FormRequest
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
            'payment_phone' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ];
    }
}
