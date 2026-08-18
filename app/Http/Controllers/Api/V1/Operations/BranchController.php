<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginated = Location::query()
            ->where('company_id', $this->currentCompany()->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, BranchResource::collection($paginated->items())->resolve()),
            'Branches retrieved',
        );
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $company = $this->currentCompany();
        $code = $request->input('code') ?: Str::slug($request->string('name')->toString());

        $branch = Location::query()->create([
            'company_id' => $company->id,
            'name' => $request->string('name'),
            'code' => $code,
            'description' => $request->input('description'),
            'address' => $request->input('address'),
            'timezone' => $company->timezone,
            'status' => 'active',
        ]);

        return $this->success((new BranchResource($branch))->resolve(), 'Branch created', 201);
    }

    public function show(Location $branch): JsonResponse
    {
        $this->assertCompany($branch->company_id);

        return $this->success((new BranchResource($branch))->resolve(), 'Branch retrieved');
    }

    public function update(StoreBranchRequest $request, Location $branch): JsonResponse
    {
        $this->assertCompany($branch->company_id);
        $branch->fill($request->validated())->save();

        return $this->success((new BranchResource($branch))->resolve(), 'Branch updated');
    }

    public function destroy(Location $branch): JsonResponse
    {
        $this->assertCompany($branch->company_id);
        $branch->delete();

        return $this->success([], 'Branch deleted');
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
