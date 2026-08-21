<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreVoucherRequest;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = Voucher::query()
            ->with(['router', 'internetPlan'])
            ->where('company_id', $this->currentCompany()->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('router_id'), fn ($query) => $query->where('network_device_id', $request->integer('router_id')))
            ->when($request->filled('package_id'), fn ($query) => $query->where('internet_plan_id', $request->integer('package_id')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        $paginated->getCollection()->each->syncExpiryStatus();

        return $this->success(
            $this->paginated($paginated, VoucherResource::collection($paginated->items())->resolve()),
            'Vouchers retrieved',
        );
    }

    public function store(StoreVoucherRequest $request): JsonResponse
    {
        $vouchers = $this->voucherService->create(
            $this->currentCompany(),
            $request->validated(),
            $request->user(),
        );

        return $this->success([
            'quantity' => $vouchers->count(),
            'items' => VoucherResource::collection($vouchers)->resolve(),
        ], 'Vouchers created', 201);
    }

    public function revoke(Request $request, Voucher $voucher): JsonResponse
    {
        $this->assertCompany($voucher->company_id);

        $voucher = $this->voucherService->revoke($voucher, $request->user());

        return $this->success((new VoucherResource($voucher))->resolve(), 'Voucher revoked');
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
