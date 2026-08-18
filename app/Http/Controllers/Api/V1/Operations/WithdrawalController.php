<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreWithdrawalRequest;
use App\Http\Resources\WithdrawalResource;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalService $withdrawalService) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->currentCompany();
        $wallet = Wallet::query()->where('company_id', $company->id)->where('currency', 'TZS')->first();

        $paginated = Withdrawal::query()
            ->where('company_id', $company->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success([
            'wallet_balance' => (float) ($wallet?->balance ?? 0),
            'currency' => 'TZS',
            ...$this->paginated($paginated, WithdrawalResource::collection($paginated->items())->resolve()),
        ], 'Withdrawals retrieved');
    }

    public function store(StoreWithdrawalRequest $request): JsonResponse
    {
        $withdrawal = $this->withdrawalService->create($this->currentCompany(), $request->validated(), $request->user());

        return $this->success((new WithdrawalResource($withdrawal))->resolve(), 'Withdrawal requested', 201);
    }

    public function show(Withdrawal $withdrawal): JsonResponse
    {
        if ($withdrawal->company_id !== $this->currentCompany()->id) {
            abort(404);
        }

        return $this->success((new WithdrawalResource($withdrawal))->resolve(), 'Withdrawal retrieved');
    }
}
