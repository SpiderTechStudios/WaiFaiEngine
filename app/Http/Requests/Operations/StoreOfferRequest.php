<?php

namespace App\Http\Requests\Operations;

use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOfferRequest extends FormRequest
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
            'title' => [$requiredOnCreate, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'duration_unit' => ['nullable', 'string', Rule::in(Offer::DURATION_UNITS)],
            'max_claims' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'internet_plan_id' => ['nullable', 'integer'],
            'router_ids' => ['nullable', 'array'],
            'router_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (filled($this->input('starts_at')) && filled($this->input('ends_at'))
                && strtotime((string) $this->input('ends_at')) < strtotime((string) $this->input('starts_at'))) {
                $validator->errors()->add('ends_at', 'The offer end time must be after the start time.');
            }

            if ($this->isMethod('post') && ! filled($this->input('internet_plan_id'))) {
                if (! filled($this->input('duration'))) {
                    $validator->errors()->add('duration', 'Provide a duration or select a package for the offer.');
                }

                if (! filled($this->input('duration_unit'))) {
                    $validator->errors()->add('duration_unit', 'Provide a duration unit or select a package for the offer.');
                }
            }
        });
    }
}
