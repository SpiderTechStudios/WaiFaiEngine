<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $companyService) {}

    public function index(Request $request): JsonResponse
    {
        $companies = $request->user()
            ->companies()
            ->wherePivot('status', '!=', 'removed')
            ->orderBy('name')
            ->get();

        return $this->success(CompanyResource::collection($companies)->resolve(), 'Companies retrieved');
    }

    public function store(CreateCompanyRequest $request): JsonResponse
    {
        $company = $this->companyService->create($request->user(), $request->validated());

        return $this->success((new CompanyResource($company))->resolve(), 'Company created successfully', 201);
    }

    public function show(Request $request, Company $company): JsonResponse
    {
        $this->authorizeCompany($request, $company);

        return $this->success((new CompanyResource($company))->resolve(), 'Company retrieved');
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $this->authorizeCompany($request, $company, Permissions::COMPANIES_UPDATE);

        $company = $this->companyService->update($company, $request->validated(), $request->user());

        return $this->success((new CompanyResource($company))->resolve(), 'Company updated successfully');
    }

    private function authorizeCompany(Request $request, Company $company, ?string $permission = null): void
    {
        $user = $request->user();

        if ($user->is_superadmin) {
            return;
        }

        $membership = $user->membershipFor($company);

        if (! $membership || $membership->status !== 'active') {
            abort(403, 'You do not have access to this company.');
        }

        if ($permission && ! $user->hasCompanyPermission($permission, $company)) {
            abort(403, 'You are not authorized to perform this action.');
        }
    }
}
