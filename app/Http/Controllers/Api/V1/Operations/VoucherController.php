<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreVoucherBatchRequest;
use App\Http\Resources\VoucherBatchResource;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = Voucher::query()
            ->where('company_id', $this->currentCompany()->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, VoucherResource::collection($paginated->items())->resolve()),
            'Vouchers retrieved',
        );
    }

    public function store(StoreVoucherBatchRequest $request): JsonResponse
    {
        $batch = $this->voucherService->create($this->currentCompany(), $request->validated(), $request->user());

        return $this->success((new VoucherBatchResource($batch))->resolve(), 'Vouchers created', 201);
    }

    public function batches(Request $request): JsonResponse
    {
        $paginated = VoucherBatch::query()
            ->with('internetPlan')
            ->where('company_id', $this->currentCompany()->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, VoucherBatchResource::collection($paginated->items())->resolve()),
            'Voucher batches retrieved',
        );
    }
}
