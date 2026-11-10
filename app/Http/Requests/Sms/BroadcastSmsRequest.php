<?php

namespace App\Http\Requests\Sms;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastSmsRequest extends FormRequest
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
            'text' => ['required', 'string', 'max:1000'],
            'recipients' => ['nullable', 'array', 'max:2000'],
            'recipients.*' => ['required', 'string', 'max:32'],
            'audience' => ['nullable', 'in:active,all'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'string', 'max:11'],
        ];
    }
}
