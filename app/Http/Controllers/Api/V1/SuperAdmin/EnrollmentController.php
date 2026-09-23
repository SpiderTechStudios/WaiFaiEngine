<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentAdminResource;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    /**
     * Enrollments that registered but never completed payment, with their
     * current provider payment status.
     */
    public function index(Request $request): JsonResponse
    {
        $enrollments = Enrollment::query()
            ->with(['payments' => fn ($query) => $query->latest('id')])
            ->where('status', '!=', Enrollment::STATUS_COMPLETED)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status'))
            )
            ->when($request->boolean('expired_only'), fn ($query) => $query->where('status', Enrollment::STATUS_EXPIRED))
            ->when($request->boolean('active_only'), fn ($query) => $query->whereIn('status', Enrollment::ACTIVE_RESERVATION_STATUSES))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($inner) use ($search): void {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('payment_phone', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%");
                });
            })
            ->latest('created_at');

        $paginated = $enrollments->paginate($request->integer('per_page', 15));

        return $this->success([
            ...$this->paginated(
                $paginated,
                EnrollmentAdminResource::collection($paginated->items())->resolve(),
            ),
            'summary' => $this->summary(),
        ], 'Stuck enrollments retrieved');
    }

    public function show(Enrollment $enrollment): JsonResponse
    {
        $enrollment->load(['payments' => fn ($query) => $query->latest('id')]);

        return $this->success(
            (new EnrollmentAdminResource($enrollment))->resolve(),
            'Enrollment retrieved',
        );
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        $counts = Enrollment::query()
            ->where('status', '!=', Enrollment::STATUS_COMPLETED)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pending = (int) ($counts[Enrollment::STATUS_PENDING_PAYMENT] ?? 0);
        $failed = (int) ($counts[Enrollment::STATUS_PAYMENT_FAILED] ?? 0);
        $processing = (int) ($counts[Enrollment::STATUS_PROCESSING_PAYMENT] ?? 0);
        $expired = (int) ($counts[Enrollment::STATUS_EXPIRED] ?? 0);
        $cancelled = (int) ($counts[Enrollment::STATUS_CANCELLED] ?? 0);

        return [
            'total' => $pending + $failed + $processing + $expired + $cancelled,
            'pending_payment' => $pending,
            'payment_failed' => $failed,
            'processing_payment' => $processing,
            'expired' => $expired,
            'cancelled' => $cancelled,
        ];
    }
}
