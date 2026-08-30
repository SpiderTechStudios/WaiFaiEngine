<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstallationRequestResource;
use App\Http\Resources\PlatformPaymentResource;
use App\Models\InstallationRequest;
use App\Services\InstallationRequestService;
use App\Services\PlatformPaymentService;
use App\Support\PlatformPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstallationRequestController extends Controller
{
    public function __construct(
        private InstallationRequestService $installationRequestService,
        private PlatformPaymentService $platformPaymentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = InstallationRequest::query()
            ->with(['items'])
            ->where('company_id', $this->currentCompany()->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated(
                $paginated,
                collect($paginated->items())->map(
                    fn (InstallationRequest $row) => (new InstallationRequestResource($row))->resolve()
                )->all(),
            ),
            'Installation requests retrieved',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_type' => ['required', Rule::in(PlatformPricing::installationServiceTypes())],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $model = $this->installationRequestService->create(
            $this->currentCompany(),
            $request->user(),
            $data,
        );

        return $this->success(
            (new InstallationRequestResource($model))->resolve(),
            'Installation request created',
            201,
        );
    }

    public function show(InstallationRequest $installationRequest): JsonResponse
    {
        $this->assertCompany($installationRequest);

        $installationRequest->load(['items', 'statusHistories.actor', 'updates.actor']);

        return $this->success(
            (new InstallationRequestResource($installationRequest))->resolve(),
            'Installation request retrieved',
        );
    }

    public function startPayment(Request $request, InstallationRequest $installationRequest): JsonResponse
    {
        $this->assertCompany($installationRequest);

        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payment = $this->platformPaymentService->startInstallationPayment($installationRequest, $data);

        return $this->success(
            (new PlatformPaymentResource($payment))->resolve(),
            'Installation payment initiated',
            201,
        );
    }

    public function showPayment(InstallationRequest $installationRequest, int $payment): JsonResponse
    {
        $this->assertCompany($installationRequest);

        $paymentModel = $this->platformPaymentService->findForInstallationOrFail(
            $installationRequest,
            $payment,
        );

        return $this->success(
            (new PlatformPaymentResource($paymentModel))->resolve(),
            'Installation payment retrieved',
        );
    }

    private function assertCompany(InstallationRequest $installationRequest): void
    {
        if ($installationRequest->company_id !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
