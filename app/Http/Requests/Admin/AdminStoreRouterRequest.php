<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Operations\StoreRouterRequest;
use Illuminate\Validation\Rule;

class AdminStoreRouterRequest extends StoreRouterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'company_id' => [
                Rule::requiredIf(fn () => $this->isMethod('post')),
                'sometimes',
                'integer',
                'exists:companies,id',
            ],
        ]);
    }
}
