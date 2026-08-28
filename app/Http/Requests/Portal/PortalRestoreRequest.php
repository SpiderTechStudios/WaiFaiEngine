<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class PortalRestoreRequest extends FormRequest
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
            'customer_phone' => ['required', 'string', 'max:50'],
            'mac_address' => ['nullable', 'string', 'max:32'],
        ];
    }
}
