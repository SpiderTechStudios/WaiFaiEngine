<?php

namespace App\Http\Requests\Operations;

use App\Models\InternetPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePackageRequest extends FormRequest
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

        $durationRules = ['nullable', 'integer', 'min:1'];
        if ($this->isMethod('post')) {
            // A duration is needed to know when access ends, unless the data is unlimited.
            $durationRules[] = 'required_unless:duration_unit,UNLIMITED_DATA';
        }

        return [
            'name' => [$requiredOnCreate, 'string', 'max:255'],
            'price' => [$requiredOnCreate, 'numeric', 'min:0'],
            'duration' => $durationRules,
            'duration_unit' => [$requiredOnCreate, 'string', Rule::in(InternetPlan::DURATION_UNITS)],
            'badge' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(InternetPlan::STATUSES)],
            'speed_download_mbps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'speed_upload_mbps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'data_cap_mb' => ['nullable', 'integer', 'min:0'],
            'devices_allowed' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'visible_on_portal' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('duration_unit') === 'UNLIMITED_DATA' && filled($this->input('duration'))) {
                $validator->errors()->add('duration', 'Duration is not used when duration_unit is UNLIMITED_DATA.');
            }
        });
    }
}
