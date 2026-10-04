<?php

namespace App\Http\Requests\Operations;

use App\Models\NetworkDevice;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IncomeReportRequest extends FormRequest
{
    public const MAX_RANGE_DAYS = 366;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->company?->id;

        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'compare' => ['nullable', 'boolean'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('network_stations', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'router_id' => [
                'nullable',
                'integer',
                Rule::exists('network_devices', 'id')->where('company_id', $companyId)->where('type', 'router'),
            ],
            'format' => ['sometimes', 'required', 'in:csv,pdf'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $company = app(CompanyContext::class)->company;
                $timezone = $company?->reportingTimezone() ?? (string) config('platform.reporting_timezone', 'Africa/Dar_es_Salaam');
                $today = Carbon::now($timezone)->startOfDay();
                [$from, $to] = $this->range($timezone);

                if ($to->gt($today)) {
                    $validator->errors()->add('to', 'The to date cannot be in the future.');
                }

                if ($to->lt($from)) {
                    $validator->errors()->add('to', 'The to date must be on or after the from date.');
                }

                if ($from->diffInDays($to) + 1 > self::MAX_RANGE_DAYS) {
                    $validator->errors()->add('to', 'The date range cannot be longer than '.self::MAX_RANGE_DAYS.' days.');
                }

                if ($this->filled('branch_id') && $this->filled('router_id')) {
                    $routerBranch = NetworkDevice::query()->whereKey($this->integer('router_id'))->value('network_station_id');
                    if ((int) $routerBranch !== $this->integer('branch_id')) {
                        $validator->errors()->add('router_id', 'The router does not belong to the selected branch.');
                    }
                }
            },
        ];
    }

    /**
     * Local start-of-day bounds for the requested (or default) range.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(string $timezone): array
    {
        $today = Carbon::now($timezone)->startOfDay();

        $to = $this->filled('to')
            ? Carbon::createFromFormat('Y-m-d', (string) $this->input('to'), $timezone)->startOfDay()
            : $today->copy();

        $from = $this->filled('from')
            ? Carbon::createFromFormat('Y-m-d', (string) $this->input('from'), $timezone)->startOfDay()
            : $to->copy()->subDays(13);

        return [$from, $to];
    }
}
