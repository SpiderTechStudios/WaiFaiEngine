<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayoutFeesRequest extends FormRequest
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
            'platform_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'platform_fixed' => ['required', 'numeric', 'min:0'],
            'provider_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'provider_fixed' => ['required', 'numeric', 'min:0'],
        ];
    }
}
