<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreSessionRequest;
use App\Http\Resources\NetworkSessionResource;
use App\Models\NetworkSession;
use App\Services\NetworkSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotspotSessionController extends Controller
{
    public function __construct(private NetworkSessionService $sessionService) {}

    public function index(Request $request): JsonResponse
    {
        $query = NetworkSession::query()
            ->with(['customer', 'networkDevice', 'internetPlan', 'paymentTransaction', 'accessGrant'])
            ->where('company_id', $this->currentCompany()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $paginated = $query->latest('id')->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, NetworkSessionResource::collection($paginated->items())->resolve()),
            'Sessions retrieved',
        );
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $session = $this->sessionService->create(
            $this->currentCompany(),
            $request->validated(),
            $request->user(),
        );

        return $this->success(
            (new NetworkSessionResource($session))->resolve(),
            'Session created',
            201,
        );
    }

    public function show(NetworkSession $session): JsonResponse
    {
        if ($session->company_id !== $this->currentCompany()->id) {
            abort(404);
        }

        return $this->success(
            (new NetworkSessionResource($session->load([
                'customer',
                'networkDevice',
                'internetPlan',
                'paymentTransaction',
                'accessGrant',
            ])))->resolve(),
            'Session retrieved',
        );
    }
}
