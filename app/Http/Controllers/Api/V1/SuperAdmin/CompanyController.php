<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $companyService) {}

    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()->latest()->paginate($request->integer('per_page', 15));

        return $this->success([
            'items' => CompanyResource::collection($companies->items())->resolve(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'total' => $companies->total(),
            ],
        ], 'Companies retrieved');
    }

    public function show(Company $company): JsonResponse
    {
        return $this->success((new CompanyResource($company))->resolve(), 'Company retrieved');
    }

    public function suspend(Request $request, Company $company): JsonResponse
    {
        $company = $this->companyService->suspend($company, $request->user());

        return $this->success((new CompanyResource($company))->resolve(), 'Company suspended');
    }

    public function activate(Request $request, Company $company): JsonResponse
    {
        $company = $this->companyService->activate($company, $request->user());

        return $this->success((new CompanyResource($company))->resolve(), 'Company activated');
    }
}
