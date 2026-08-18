<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\PlanPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackageService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(Company $company, array $data, User $actor): InternetPlan
    {
        return DB::transaction(function () use ($company, $data, $actor) {
            $plan = InternetPlan::query()->create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($company->id, $data['name']),
                'description' => $data['description'] ?? null,
                'duration' => $data['duration'] ?? null,
                'duration_unit' => $data['duration_unit'] ?? null,
                'data_limit' => $data['data_limit'] ?? null,
                'data_limit_unit' => $data['data_limit_unit'] ?? null,
                'download_speed' => $data['download_speed'] ?? null,
                'upload_speed' => $data['upload_speed'] ?? null,
                'speed_unit' => $data['speed_unit'] ?? 'Mbps',
                'max_devices' => $data['max_devices'] ?? 1,
                'activation_mode' => $data['activation_mode'] ?? 'immediate',
                'status' => 'active',
            ]);

            PlanPrice::query()->create([
                'company_id' => $company->id,
                'internet_plan_id' => $plan->id,
                'currency' => $data['currency'] ?? 'TZS',
                'amount' => $data['price'],
                'status' => 'active',
                'valid_from' => now(),
            ]);

            $this->auditLogger->log('package_created', $actor, $company->id, InternetPlan::class, $plan->id);

            return $plan->load('prices');
        });
    }

    public function update(InternetPlan $plan, array $data, User $actor): InternetPlan
    {
        return DB::transaction(function () use ($plan, $data, $actor) {
            $plan->fill(collect($data)->except(['price', 'currency'])->all())->save();

            if (isset($data['price'])) {
                $plan->prices()
                    ->where('status', 'active')
                    ->update(['status' => 'inactive', 'valid_until' => now()]);

                PlanPrice::query()->create([
                    'company_id' => $plan->company_id,
                    'internet_plan_id' => $plan->id,
                    'currency' => $data['currency'] ?? $plan->prices()->latest('id')->value('currency') ?? 'TZS',
                    'amount' => $data['price'],
                    'status' => 'active',
                    'valid_from' => now(),
                ]);
            }

            $this->auditLogger->log('package_updated', $actor, $plan->company_id, InternetPlan::class, $plan->id);

            return $plan->load('prices');
        });
    }

    public function delete(InternetPlan $plan, User $actor): void
    {
        $plan->forceFill(['status' => 'inactive'])->save();
        $plan->delete();
        $this->auditLogger->log('package_deleted', $actor, $plan->company_id, InternetPlan::class, $plan->id);
    }

    private function uniqueSlug(int $companyId, string $name): string
    {
        $base = Str::slug($name) ?: 'package';
        $slug = $base;
        $i = 1;

        while (InternetPlan::query()->where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
