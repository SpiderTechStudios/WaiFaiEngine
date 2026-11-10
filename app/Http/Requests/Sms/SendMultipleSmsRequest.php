<?php

namespace App\Http\Requests\Sms;

use Illuminate\Foundation\Http\FormRequest;

class SendMultipleSmsRequest extends FormRequest
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
            'to' => ['required', 'array', 'min:1', 'max:2000'],
            'to.*' => ['required', 'string', 'max:32'],
            'text' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'string', 'max:11'],
        ];
    }
}
