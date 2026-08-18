<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'duration_unit' => ['nullable', 'string', 'in:minute,hour,day,week,month'],
            'data_limit' => ['nullable', 'integer', 'min:0'],
            'data_limit_unit' => ['nullable', 'string', 'in:MB,GB'],
            'download_speed' => ['nullable', 'integer', 'min:0'],
            'upload_speed' => ['nullable', 'integer', 'min:0'],
            'speed_unit' => ['nullable', 'string', 'max:20'],
            'max_devices' => ['nullable', 'integer', 'min:1'],
            'price' => [$this->isMethod('post') ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
