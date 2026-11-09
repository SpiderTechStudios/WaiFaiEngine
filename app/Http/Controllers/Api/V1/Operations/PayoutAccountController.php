<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StorePayoutAccountRequest;
use App\Http\Resources\PayoutAccountResource;
use App\Models\PayoutAccount;
use App\Services\PayoutAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutAccountController extends Controller
{
    public function __construct(private PayoutAccountService $payoutAccountService) {}

    public function index(): JsonResponse
    {
        $accounts = $this->payoutAccountService->list($this->currentCompany());

        return $this->success(
            PayoutAccountResource::collection($accounts)->resolve(),
            'Payout accounts retrieved',
        );
    }

    public function store(StorePayoutAccountRequest $request): JsonResponse
    {
        $account = $this->payoutAccountService->create(
            $this->currentCompany(),
            $request->validated(),
            $request->user(),
        );

        return $this->success((new PayoutAccountResource($account))->resolve(), 'Payout account saved', 201);
    }

    public function update(StorePayoutAccountRequest $request, PayoutAccount $payoutAccount): JsonResponse
    {
        $account = $this->payoutAccountService->update(
            $this->currentCompany(),
            $payoutAccount,
            $request->validated(),
            $request->user(),
        );

        return $this->success((new PayoutAccountResource($account))->resolve(), 'Payout account updated');
    }

    public function destroy(Request $request, PayoutAccount $payoutAccount): JsonResponse
    {
        $this->payoutAccountService->delete($this->currentCompany(), $payoutAccount, $request->user());

        return $this->success([], 'Payout account removed');
    }

    public function setDefault(Request $request, PayoutAccount $payoutAccount): JsonResponse
    {
        $account = $this->payoutAccountService->setDefault(
            $this->currentCompany(),
            $payoutAccount,
            $request->user(),
        );

        return $this->success((new PayoutAccountResource($account))->resolve(), 'Default payout account updated');
    }
}
