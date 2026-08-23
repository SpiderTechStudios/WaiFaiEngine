<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceSetupRequest extends FormRequest
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
            'portal_subdomain' => ['sometimes', 'nullable', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'ruijie_account_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ruijie_password' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
