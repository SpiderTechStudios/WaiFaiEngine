<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstallationRequestResource;
use App\Models\InstallationRequest;
use App\Services\InstallationRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminInstallationRequestController extends Controller
{
    public function __construct(private InstallationRequestService $installationRequestService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = InstallationRequest::query()
            ->with(['items', 'company'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('fulfillment_status'), fn ($q) => $q->where('fulfillment_status', $request->string('fulfillment_status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated(
                $paginated,
                collect($paginated->items())->map(
                    fn (InstallationRequest $row) => (new InstallationRequestResource($row, true))->resolve()
                )->all(),
            ),
            'Installation requests retrieved',
        );
    }

    public function show(InstallationRequest $installationRequest): JsonResponse
    {
        $installationRequest->load(['items', 'statusHistories.actor', 'updates.actor', 'company']);

        return $this->success(
            (new InstallationRequestResource($installationRequest, true))->resolve(),
            'Installation request retrieved',
        );
    }

    public function updateFulfillment(Request $request, InstallationRequest $installationRequest): JsonResponse
    {
        $data = $request->validate([
            'fulfillment_status' => [
                'required',
                Rule::in([
                    ...InstallationRequest::FULFILLMENT_FLOW,
                    InstallationRequest::FULFILLMENT_CANCELLED,
                ]),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $model = $this->installationRequestService->updateFulfillment(
            $installationRequest,
            $data,
            $request->user(),
        );

        return $this->success(
            (new InstallationRequestResource($model, true))->resolve(),
            'Fulfillment status updated',
        );
    }

    public function addUpdate(Request $request, InstallationRequest $installationRequest): JsonResponse
    {
        $data = $request->validate([
            'visibility' => ['required', Rule::in(['customer', 'internal'])],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $update = $this->installationRequestService->addUpdate(
            $installationRequest,
            $data,
            $request->user(),
        );

        return $this->success([
            'id' => $update->id,
            'visibility' => $update->visibility,
            'body' => $update->body,
            'created_at' => $update->created_at,
        ], 'Update added', 201);
    }
}
