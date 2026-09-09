<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceCategoryRequest extends FormRequest
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
        $categoryId = $this->route('deviceCategory')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('device_categories', 'name')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
