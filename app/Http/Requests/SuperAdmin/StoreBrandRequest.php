<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:brands,slug'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
