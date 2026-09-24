<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Services\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(private OfferService $offerService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = Offer::query()
            ->with(['internetPlan', 'routers'])
            ->where('company_id', $this->currentCompany()->id)
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            $this->paginated($paginated, OfferResource::collection($paginated->items())->resolve()),
            'Offers retrieved',
        );
    }

    public function store(StoreOfferRequest $request): JsonResponse
    {
        $offer = $this->offerService->create(
            $this->currentCompany(),
            $request->validated(),
            $request->user(),
        );

        return $this->success((new OfferResource($offer))->resolve(), 'Offer created', 201);
    }

    public function show(Offer $offer): JsonResponse
    {
        $this->assertCompany($offer->company_id);
        $offer->load(['internetPlan', 'routers']);

        return $this->success((new OfferResource($offer))->resolve(), 'Offer retrieved');
    }

    public function update(StoreOfferRequest $request, Offer $offer): JsonResponse
    {
        $this->assertCompany($offer->company_id);
        $offer = $this->offerService->update($offer, $request->validated(), $request->user());

        return $this->success((new OfferResource($offer))->resolve(), 'Offer updated');
    }

    public function destroy(Request $request, Offer $offer): JsonResponse
    {
        $this->assertCompany($offer->company_id);
        $this->offerService->delete($offer, $request->user());

        return $this->success([], 'Offer deleted');
    }

    public function activate(Request $request, Offer $offer): JsonResponse
    {
        $this->assertCompany($offer->company_id);
        $offer = $this->offerService->activate($offer, $request->user());

        return $this->success((new OfferResource($offer))->resolve(), 'Offer activated');
    }

    public function deactivate(Request $request, Offer $offer): JsonResponse
    {
        $this->assertCompany($offer->company_id);
        $offer = $this->offerService->deactivate($offer, $request->user());

        return $this->success((new OfferResource($offer))->resolve(), 'Offer deactivated');
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->currentCompany()->id) {
            abort(404);
        }
    }
}
