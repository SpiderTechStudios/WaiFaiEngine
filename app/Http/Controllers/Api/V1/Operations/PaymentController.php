<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = PaymentTransaction::query()
            ->with(['customer', 'internetPlan'])
            ->where('company_id', $this->currentCompany()->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, PaymentResource::collection($paginated->items())->resolve()),
            'Payments retrieved',
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->create($this->currentCompany(), $request->validated(), $request->user());

        return $this->success((new PaymentResource($payment))->resolve(), 'Payment recorded', 201);
    }

    public function show(PaymentTransaction $payment): JsonResponse
    {
        if ($payment->company_id !== $this->currentCompany()->id) {
            abort(404);
        }

        return $this->success(
            (new PaymentResource($payment->load(['customer', 'internetPlan'])))->resolve(),
            'Payment retrieved',
        );
    }

    public function getAllPayments(Request $request): JsonResponse
    {
        $paginated = PaymentTransaction::query()
            ->with(['customer', 'internetPlan', 'company'])
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->integer('company_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, PaymentResource::collection($paginated->items())->resolve()),
            'All payments retrieved',
        );
    }

    public function getPayment(PaymentTransaction $payment): JsonResponse
    {
        return $this->success(
            (new PaymentResource($payment->load(['customer', 'internetPlan', 'company'])))->resolve(),
            'Payment retrieved',
        );
    }
}
