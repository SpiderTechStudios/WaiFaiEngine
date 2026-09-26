<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
            'router_id' => ['nullable', 'integer'],
            'expense_type_id' => [$requiredOnCreate, 'integer'],
            'description' => [$requiredOnCreate, 'string', 'max:255'],
            'amount' => [$requiredOnCreate, 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'paid_at' => [$requiredOnCreate, 'date'],
            'paid_to' => [$requiredOnCreate, 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Category and source are derived from the expense type and the
            // authenticated actor. Never trust them from the client.
            'category' => ['prohibited'],
            'source' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }
}
