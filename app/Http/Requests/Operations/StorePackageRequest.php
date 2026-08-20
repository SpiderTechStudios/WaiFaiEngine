<?php

namespace App\Http\Requests\Operations;

use App\Models\InternetPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $requiredOnCreate = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$requiredOnCreate, 'string', 'max:255'],
            'price' => [$requiredOnCreate, 'numeric', 'min:0'],
            'duration' => ['nullable', 'integer', 'min:1', 'required_unless:duration_unit,UNLIMITED_DATA'],
            'duration_unit' => [$requiredOnCreate, 'string', Rule::in(InternetPlan::DURATION_UNITS)],
            'badge' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('duration_unit') === 'UNLIMITED_DATA' && filled($this->input('duration'))) {
                $validator->errors()->add('duration', 'Duration is not used when duration_unit is UNLIMITED_DATA.');
            }
        });
    }
}
