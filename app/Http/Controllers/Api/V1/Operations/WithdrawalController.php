<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\ConfirmWithdrawalRequest;
use App\Http\Requests\Operations\PreviewWithdrawalRequest;
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
            ->with(['paymentMethod', 'payoutAccount', 'paymentProvider'])
            ->where('company_id', $company->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success([
            'wallet_balance' => (float) ($wallet?->balance ?? 0),
            'currency' => 'TZS',
            ...$this->paginated($paginated, WithdrawalResource::collection($paginated->items())->resolve()),
        ], 'Withdrawals retrieved');
    }

    public function preview(PreviewWithdrawalRequest $request): JsonResponse
    {
        $quote = $this->withdrawalService->preview($this->currentCompany(), $request->validated());

        return $this->success($quote, 'Withdrawal quote');
    }

    public function store(StoreWithdrawalRequest $request): JsonResponse
    {
        $withdrawal = $this->withdrawalService->request($this->currentCompany(), $request->validated(), $request->user());

        return $this->success((new WithdrawalResource($withdrawal))->resolve(), 'Withdrawal requested. Enter the confirmation code sent to you.', 201);
    }

    public function confirm(ConfirmWithdrawalRequest $request, Withdrawal $withdrawal): JsonResponse
    {
        if ($withdrawal->company_id !== $this->currentCompany()->id) {
            abort(404);
        }

        $withdrawal = $this->withdrawalService->confirm(
            $this->currentCompany(),
            $withdrawal,
            (string) $request->validated('otp'),
            $request->user(),
        );

        return $this->success((new WithdrawalResource($withdrawal))->resolve(), 'Withdrawal confirmed and payout started');
    }

    public function resendOtp(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        if ($withdrawal->company_id !== $this->currentCompany()->id) {
            abort(404);
        }

        $withdrawal = $this->withdrawalService->resendOtp(
            $this->currentCompany(),
            $withdrawal,
            $request->user(),
        );

        return $this->success((new WithdrawalResource($withdrawal))->resolve(), 'Confirmation code resent');
    }

    public function show(Withdrawal $withdrawal): JsonResponse
    {
        if ($withdrawal->company_id !== $this->currentCompany()->id) {
            abort(404);
        }

        $withdrawal->load(['paymentMethod', 'payoutAccount', 'paymentProvider']);

        return $this->success((new WithdrawalResource($withdrawal))->resolve(), 'Withdrawal retrieved');
    }

    public function cancel(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        if ($withdrawal->company_id !== $this->currentCompany()->id) {
            abort(404);
        }

        $withdrawal = $this->withdrawalService->cancel($withdrawal, $request->user());

        return $this->success((new WithdrawalResource($withdrawal))->resolve(), 'Withdrawal cancelled');
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
        $processing = $byStatus->get('processing');
        $completed = $byStatus->get('completed') ?? $byStatus->get('paid') ?? $byStatus->get('success');
        $failed = $byStatus->get('failed');
        $cancelled = $byStatus->get('cancelled');

        $fees = Withdrawal::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['completed', 'paid', 'success', 'processing'])
            ->selectRaw('COALESCE(SUM(platform_fee), 0) as platform_fee, COALESCE(SUM(provider_fee), 0) as provider_fee, COALESCE(SUM(fee_total), 0) as fee_total, COALESCE(SUM(net_amount), 0) as net_amount')
            ->first();

        return $this->success([
            'currency' => 'TZS',
            'wallet_balance' => (float) ($wallet?->balance ?? 0),
            'pending_count' => (int) ($pending?->total ?? 0),
            'pending_amount' => (float) ($pending?->amount ?? 0),
            'processing_count' => (int) ($processing?->total ?? 0),
            'processing_amount' => (float) ($processing?->amount ?? 0),
            'completed_count' => (int) ($completed?->total ?? 0),
            'completed_amount' => (float) ($completed?->amount ?? 0),
            'failed_count' => (int) ($failed?->total ?? 0),
            'failed_amount' => (float) ($failed?->amount ?? 0),
            'cancelled_count' => (int) ($cancelled?->total ?? 0),
            'cancelled_amount' => (float) ($cancelled?->amount ?? 0),
            'platform_fee_total' => (float) ($fees?->platform_fee ?? 0),
            'provider_fee_total' => (float) ($fees?->provider_fee ?? 0),
            'fee_total' => (float) ($fees?->fee_total ?? 0),
            'net_paid_total' => (float) ($fees?->net_amount ?? 0),
            'total_count' => (int) Withdrawal::query()->where('company_id', $company->id)->count(),
            'total_amount' => (float) Withdrawal::query()->where('company_id', $company->id)->sum('amount'),
        ], 'Withdrawal stats retrieved');
    }

    public function adminGetWithdrawals(Request $request): JsonResponse
    {
        $paginated = Withdrawal::query()
            ->with(['company', 'paymentMethod', 'payoutAccount', 'paymentProvider'])
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
            (new WithdrawalResource($withdrawal->load(['company', 'paymentMethod', 'payoutAccount', 'paymentProvider'])))->resolve(),
            'Withdrawal retrieved',
        );
    }
}
