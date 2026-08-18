<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\AddCompanyStaffRequest;
use App\Http\Requests\Company\TransferOwnershipRequest;
use App\Http\Requests\Company\UpdateStaffRoleRequest;
use App\Http\Resources\MembershipResource;
use App\Models\UserCompany;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(private StaffService $staffService) {}

    public function index(): JsonResponse
    {
        $staff = $this->currentCompany()
            ->memberships()
            ->with(['user', 'role.permissions', 'company'])
            ->where('status', '!=', 'removed')
            ->get();

        return $this->success(MembershipResource::collection($staff)->resolve(), 'Staff retrieved');
    }

    public function store(AddCompanyStaffRequest $request): JsonResponse
    {
        $membership = $this->staffService->add($request->user(), $this->currentCompany(), $request->validated());

        return $this->success((new MembershipResource($membership))->resolve(), 'Staff member added', 201);
    }

    public function show(UserCompany $membership): JsonResponse
    {
        $this->assertMembership($membership);
        $membership->load(['user', 'role.permissions', 'company']);

        return $this->success((new MembershipResource($membership))->resolve(), 'Staff member retrieved');
    }

    public function updateRole(UpdateStaffRoleRequest $request, UserCompany $membership): JsonResponse
    {
        $this->assertMembership($membership);
        $membership = $this->staffService->updateRole($request->user(), $membership, $request->string('role')->toString());

        return $this->success((new MembershipResource($membership))->resolve(), 'Staff role updated');
    }

    public function suspend(Request $request, UserCompany $membership): JsonResponse
    {
        $this->assertMembership($membership);
        $membership = $this->staffService->suspend($request->user(), $membership);

        return $this->success((new MembershipResource($membership))->resolve(), 'Staff membership suspended');
    }

    public function activate(Request $request, UserCompany $membership): JsonResponse
    {
        $this->assertMembership($membership);
        $membership = $this->staffService->activate($request->user(), $membership);

        return $this->success((new MembershipResource($membership))->resolve(), 'Staff membership activated');
    }

    public function destroy(Request $request, UserCompany $membership): JsonResponse
    {
        $this->assertMembership($membership);
        $this->staffService->remove($request->user(), $membership);

        return $this->success([], 'Staff member removed');
    }

    public function transferOwnership(TransferOwnershipRequest $request): JsonResponse
    {
        $company = $this->currentCompany();
        $membership = UserCompany::query()->findOrFail($request->integer('membership_id'));
        $this->assertMembership($membership);
        $this->staffService->transferOwnership($request->user(), $company, $membership);

        return $this->success([], 'Ownership transferred successfully');
    }

    private function assertMembership(UserCompany $membership): void
    {
        if ($membership->company_id !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
