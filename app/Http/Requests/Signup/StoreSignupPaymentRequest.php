<?php

namespace App\Http\Requests\Signup;

use Illuminate\Foundation\Http\FormRequest;

class StoreSignupPaymentRequest extends FormRequest
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
            'payment_method' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'line_items' => ['nullable', 'array', 'min:1'],
            'line_items.*.code' => ['required_with:line_items', 'string', 'max:50'],
            'line_items.*.amount' => ['required_with:line_items', 'numeric', 'min:0'],
            'line_items.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
