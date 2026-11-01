<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportVouchersWordRequest extends FormRequest
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
            'ids' => ['nullable', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'status' => ['nullable', Rule::in(['active', 'expired', 'revoked'])],
            'router_id' => ['nullable', 'integer'],
            'package_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
