<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class TransferOwnershipRequest extends FormRequest
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
            'membership_id' => ['required', 'integer', 'exists:user_companies,id'],
        ];
    }
}
