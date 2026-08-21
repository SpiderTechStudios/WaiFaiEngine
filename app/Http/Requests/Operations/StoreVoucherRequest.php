<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVoucherRequest extends FormRequest
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
            'router_id' => ['required', 'integer'],
            'package_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'custom_code' => ['nullable', 'string', 'regex:/^\d+$/', 'max:32'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (filled($this->input('custom_code')) && (int) $this->input('quantity') !== 1) {
                $validator->errors()->add(
                    'custom_code',
                    'Custom code can only be used when generating a single voucher.',
                );
            }
        });
    }
}
