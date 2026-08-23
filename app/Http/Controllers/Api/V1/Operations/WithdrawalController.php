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
use Illuminate\Support\Facades\DB;

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

    public function stats(): JsonResponse
    {
        $company = $this->currentCompany();
        $wallet = Wallet::query()->where('company_id', $company->id)->where('currency', 'TZS')->first();

        $byStatus = Withdrawal::query()
            ->where('company_id', $company->id)
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(amount), 0) as amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $pending = $byStatus->get('pending');
        $completed = $byStatus->get('completed') ?? $byStatus->get('paid') ?? $byStatus->get('success');
        $failed = $byStatus->get('failed');

        return $this->success([
            'currency' => 'TZS',
            'wallet_balance' => (float) ($wallet?->balance ?? 0),
            'pending_count' => (int) ($pending?->total ?? 0),
            'pending_amount' => (float) ($pending?->amount ?? 0),
            'completed_count' => (int) ($completed?->total ?? 0),
            'completed_amount' => (float) ($completed?->amount ?? 0),
            'failed_count' => (int) ($failed?->total ?? 0),
            'failed_amount' => (float) ($failed?->amount ?? 0),
            'total_count' => (int) Withdrawal::query()->where('company_id', $company->id)->count(),
            'total_amount' => (float) Withdrawal::query()->where('company_id', $company->id)->sum('amount'),
        ], 'Withdrawal stats retrieved');
    }

    public function adminGetWithdrawals(Request $request): JsonResponse
    {
        $paginated = Withdrawal::query()
            ->with('company')
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->integer('company_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, WithdrawalResource::collection($paginated->items())->resolve()),
            'All withdrawals retrieved',
        );
    }

    public function adminGetWithdrawal(Withdrawal $withdrawal): JsonResponse
    {
        return $this->success(
            (new WithdrawalResource($withdrawal->load('company')))->resolve(),
            'Withdrawal retrieved',
        );
    }
}
