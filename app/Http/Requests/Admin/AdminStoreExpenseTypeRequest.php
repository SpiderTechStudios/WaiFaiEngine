<?php

namespace App\Http\Requests\Admin;

use App\Models\ExpenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminStoreExpenseTypeRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => [$requiredOnCreate, 'string', Rule::in(ExpenseType::CATEGORIES)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
