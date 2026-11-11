<?php

namespace App\Http\Requests\Operations;

use App\Models\OmadaIntegration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOmadaIntegrationRequest extends FormRequest
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
            'branch_id' => ['nullable', 'integer'],
            'deployment_type' => [$requiredOnCreate, 'string', Rule::in(OmadaIntegration::DEPLOYMENT_TYPES)],
            'controller_url' => [$requiredOnCreate, 'string', 'max:255'],
            'controller_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'controller_id' => ['nullable', 'string', 'max:64'],
            'connection_method' => ['nullable', 'string', Rule::in(OmadaIntegration::CONNECTION_METHODS)],
            'client_id' => [$requiredOnCreate, 'string', 'max:255'],
            'client_secret' => [$requiredOnCreate, 'string', 'max:255'],
            'operator_username' => [$requiredOnCreate, 'string', 'max:255'],
            'operator_password' => [$requiredOnCreate, 'string', 'max:255'],
            'verify_tls' => ['nullable', 'boolean'],
            'site_id' => ['nullable', 'string', 'max:64'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
