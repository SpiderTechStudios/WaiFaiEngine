<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InternetPlan;
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
                'badge' => $data['badge'] ?? null,
                'description' => $data['description'] ?? null,
                'duration' => $data['duration_unit'] === 'UNLIMITED_DATA' ? null : $data['duration'],
                'duration_unit' => $data['duration_unit'],
                'price' => $data['price'],
                'status' => 'active',
            ]);

            $this->auditLogger->log('package_created', $actor, $company->id, InternetPlan::class, $plan->id);

            return $plan;
        });
    }

    public function update(InternetPlan $plan, array $data, User $actor): InternetPlan
    {
        if (($data['duration_unit'] ?? $plan->duration_unit) === 'UNLIMITED_DATA') {
            $data['duration'] = null;
        }

        $plan->fill($data)->save();

        $this->auditLogger->log('package_updated', $actor, $plan->company_id, InternetPlan::class, $plan->id);

        return $plan;
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
