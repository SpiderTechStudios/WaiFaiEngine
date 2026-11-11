<?php

namespace App\Http\Requests\Operations;

use App\Models\OmadaIntegration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestOmadaConnectionRequest extends FormRequest
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
            'deployment_type' => ['nullable', 'string', Rule::in(OmadaIntegration::DEPLOYMENT_TYPES)],
            'controller_url' => ['required', 'string', 'max:255'],
            'controller_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'controller_id' => ['nullable', 'string', 'max:64'],
            'connection_method' => ['nullable', 'string', Rule::in(OmadaIntegration::CONNECTION_METHODS)],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'verify_tls' => ['nullable', 'boolean'],
        ];
    }
}
