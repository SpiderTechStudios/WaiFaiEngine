<?php

namespace App\Http\Requests\Operations;

use App\Models\NetworkDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRouterRequest extends FormRequest
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
        $gatewayType = $this->input('gateway_type');
        $isMikroTik = $gatewayType === NetworkDevice::GATEWAY_MIKROTIK;
        $isRuijie = $gatewayType === NetworkDevice::GATEWAY_RUIJIE;
        $isOmada = $gatewayType === NetworkDevice::GATEWAY_OMADA;

        return [
            'gateway_type' => [$requiredOnCreate, 'string', Rule::in(NetworkDevice::GATEWAY_TYPES)],
            'name' => [$requiredOnCreate, 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:active,inactive,offline'],

            'lan_ip' => [
                Rule::requiredIf(fn () => $this->isMethod('post') && ($isMikroTik || $isRuijie)),
                'nullable',
                'ip',
            ],
            'api_host' => [
                Rule::requiredIf(fn () => $this->isMethod('post') && $isOmada),
                'nullable',
                'string',
                'max:255',
            ],
            'api_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'api_username' => [
                Rule::requiredIf(fn () => $this->isMethod('post') && $isMikroTik),
                'nullable',
                'string',
                'max:255',
            ],
            'api_password' => [
                Rule::requiredIf(fn () => $this->isMethod('post') && $isMikroTik),
                'nullable',
                'string',
                'max:255',
            ],
            'api_verify_tls' => ['nullable', 'boolean'],
            'connection_mode' => ['nullable', 'string', Rule::in(['public', 'vpn'])],
            'hotspot_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'router_identity' => ['nullable', 'string', 'max:100'],

            'gateway_id' => [
                Rule::requiredIf(fn () => $this->isMethod('post') && $isRuijie),
                'nullable',
                'string',
                'max:100',
            ],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'wifidog_port' => ['nullable', 'integer', 'min:1', 'max:65535'],

            // TP-Link Omada controller fields (stored in the isolated omada_* tables).
            'omada_integration_name' => ['nullable', 'string', 'max:255'],
            'omada_deployment_type' => ['nullable', 'string', Rule::in(['software', 'hardware', 'cloud'])],
            'omada_controller_url' => ['nullable', 'string', 'max:255'],
            'omada_controller_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'omada_controller_id' => ['nullable', 'string', 'max:64'],
            'omada_client_id' => ['nullable', 'string', 'max:255'],
            'omada_client_secret' => ['nullable', 'string', 'max:255'],
            'omada_operator_username' => ['nullable', 'string', 'max:255'],
            'omada_operator_password' => ['nullable', 'string', 'max:255'],
            'omada_verify_tls' => ['nullable', 'boolean'],
            'omada_site_id' => ['nullable', 'string', 'max:64'],
            'omada_site_name' => ['nullable', 'string', 'max:255'],
            'omada_site_timezone' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('gateway_type') !== NetworkDevice::GATEWAY_MIKROTIK) {
                return;
            }

            if ($this->input('connection_mode') === 'vpn') {
                return;
            }

            if (! filled($this->input('api_host'))) {
                return;
            }

            if ($this->isPrivateHost((string) $this->input('api_host'))) {
                $validator->errors()->add(
                    'api_host',
                    'API host must be a public IP or DDNS hostname. Do not use private addresses like 192.168.x or 10.x.',
                );
            }
        });
    }

    private function isPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host));

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
        }

        return (bool) preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $host);
    }
}
