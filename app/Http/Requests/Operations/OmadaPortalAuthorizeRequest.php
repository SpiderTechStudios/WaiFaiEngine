<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class OmadaPortalAuthorizeRequest extends FormRequest
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
            'client_session_id' => ['required', 'integer', 'min:1'],
            'payment_transaction_id' => ['nullable', 'integer', 'min:1', 'required_without:access_grant_id'],
            'access_grant_id' => ['nullable', 'integer', 'min:1', 'required_without:payment_transaction_id'],
        ];
    }
}
