<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class StoreRouterRequest extends FormRequest
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
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'mac_address' => ['nullable', 'string', 'max:50'],
            'ip_address' => ['nullable', 'ip'],
            'branch_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:active,inactive,offline'],
        ];
    }
}
