<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class SelectOmadaAccessPointsRequest extends FormRequest
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
            'device_ids' => ['present', 'array'],
            'device_ids.*' => ['string', 'max:128'],
        ];
    }
}
