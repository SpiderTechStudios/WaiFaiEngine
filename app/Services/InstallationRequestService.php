<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InstallationRequest;
use App\Models\InstallationRequestItem;
use App\Models\InstallationRequestStatusHistory;
use App\Models\InstallationRequestUpdate;
use App\Models\User;
use App\Support\PlatformPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstallationRequestService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, User $actor, array $data): InstallationRequest
    {
        $serviceType = (string) $data['service_type'];
        $quantity = (int) $data['quantity'];

        if (! in_array($serviceType, PlatformPricing::installationServiceTypes(), true)) {
            throw ValidationException::withMessages([
                'service_type' => ['Invalid service type.'],
            ]);
        }

        if ($quantity < 1 || $quantity > 100) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be between 1 and 100.'],
            ]);
        }

        $quote = PlatformPricing::installationQuote($serviceType, $quantity);

        return DB::transaction(function () use ($company, $actor, $data, $quote) {
            $request = InstallationRequest::query()->create([
                'company_id' => $company->id,
                'requested_by' => $actor->id,
                'reference' => 'INS-'.strtoupper(Str::random(8)),
                'service_type' => $quote['service_type'],
                'quantity' => $quote['quantity'],
                'unit_price' => $quote['unit_price'],
                'total_amount' => $quote['total_amount'],
                'currency' => $quote['currency'],
                'payment_status' => InstallationRequest::PAYMENT_PENDING,
                'fulfillment_status' => InstallationRequest::FULFILLMENT_REQUESTED,
                'customer_notes' => $data['customer_notes'] ?? null,
            ]);

            for ($i = 1; $i <= $quote['quantity']; $i++) {
                InstallationRequestItem::query()->create([
                    'installation_request_id' => $request->id,
                    'position' => $i,
                    'label' => 'Router '.$i,
                    'status' => 'pending',
                ]);
            }

            $this->recordHistory(
                $request,
                null,
                InstallationRequest::FULFILLMENT_REQUESTED,
                'fulfillment_status',
                $actor,
                'Installation request created',
            );

            $this->auditLogger->log(
                'installation_request_created',
                $actor,
                $company->id,
                InstallationRequest::class,
                $request->id,
                newValues: [
                    'reference' => $request->reference,
                    'service_type' => $request->service_type,
                    'quantity' => $request->quantity,
                    'total_amount' => $request->total_amount,
                ],
            );

            return $request->load(['items', 'statusHistories', 'updates']);
        });
    }

    public function markPaid(InstallationRequest $request): InstallationRequest
    {
        if ($request->payment_status === InstallationRequest::PAYMENT_PAID) {
            return $request;
        }

        $from = $request->payment_status;
        $request->forceFill([
            'payment_status' => InstallationRequest::PAYMENT_PAID,
            'paid_at' => now(),
        ])->save();

        $this->recordHistory(
            $request,
            $from,
            InstallationRequest::PAYMENT_PAID,
            'payment_status',
            null,
            'Payment confirmed',
        );

        return $request->fresh(['items', 'statusHistories', 'updates']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFulfillment(InstallationRequest $request, array $data, User $actor): InstallationRequest
    {
        if ($request->payment_status !== InstallationRequest::PAYMENT_PAID
            && ($data['fulfillment_status'] ?? null) !== InstallationRequest::FULFILLMENT_CANCELLED
        ) {
            throw ValidationException::withMessages([
                'fulfillment_status' => ['Payment must be completed before advancing fulfillment.'],
            ]);
        }

        $to = (string) $data['fulfillment_status'];
        $allowed = [
            ...InstallationRequest::FULFILLMENT_FLOW,
            InstallationRequest::FULFILLMENT_CANCELLED,
        ];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'fulfillment_status' => ['Invalid fulfillment status.'],
            ]);
        }

        return DB::transaction(function () use ($request, $data, $actor, $to) {
            $from = $request->fulfillment_status;

            $request->forceFill([
                'fulfillment_status' => $to,
                'scheduled_at' => $data['scheduled_at'] ?? $request->scheduled_at,
            ])->save();

            $this->recordHistory(
                $request,
                $from,
                $to,
                'fulfillment_status',
                $actor,
                $data['note'] ?? null,
            );

            $this->auditLogger->log(
                'installation_request_fulfillment_updated',
                $actor,
                $request->company_id,
                InstallationRequest::class,
                $request->id,
                oldValues: ['fulfillment_status' => $from],
                newValues: ['fulfillment_status' => $to],
            );

            return $request->fresh(['items', 'statusHistories.actor', 'updates.actor']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addUpdate(InstallationRequest $request, array $data, User $actor): InstallationRequestUpdate
    {
        $visibility = (string) $data['visibility'];
        if (! in_array($visibility, [
            InstallationRequestUpdate::VISIBILITY_CUSTOMER,
            InstallationRequestUpdate::VISIBILITY_INTERNAL,
        ], true)) {
            throw ValidationException::withMessages([
                'visibility' => ['Visibility must be customer or internal.'],
            ]);
        }

        $update = InstallationRequestUpdate::query()->create([
            'installation_request_id' => $request->id,
            'visibility' => $visibility,
            'body' => $data['body'],
            'actor_id' => $actor->id,
        ]);

        $this->auditLogger->log(
            'installation_request_update_added',
            $actor,
            $request->company_id,
            InstallationRequest::class,
            $request->id,
            newValues: ['visibility' => $visibility],
        );

        return $update->load('actor');
    }

    /**
     * @return list<array{key: string, label: string, completed: bool, current: bool}>
     */
    public function progressSteps(InstallationRequest $request): array
    {
        $current = $request->fulfillment_status;
        $currentIndex = array_search($current, InstallationRequest::FULFILLMENT_FLOW, true);

        $labels = [
            InstallationRequest::FULFILLMENT_REQUESTED => 'Requested',
            InstallationRequest::FULFILLMENT_PROCESSING => 'Processing',
            InstallationRequest::FULFILLMENT_ON_SITE => 'On Site',
            InstallationRequest::FULFILLMENT_DELIVERED => 'Delivered',
            InstallationRequest::FULFILLMENT_ACTIVE => 'Active',
        ];

        $steps = [];
        foreach (InstallationRequest::FULFILLMENT_FLOW as $index => $key) {
            $steps[] = [
                'key' => $key,
                'label' => $labels[$key],
                'completed' => $currentIndex !== false && $index < $currentIndex
                    || $current === InstallationRequest::FULFILLMENT_ACTIVE && $key === InstallationRequest::FULFILLMENT_ACTIVE,
                'current' => $key === $current,
            ];
        }

        if ($current === InstallationRequest::FULFILLMENT_ACTIVE) {
            foreach ($steps as &$step) {
                $step['completed'] = true;
                $step['current'] = $step['key'] === InstallationRequest::FULFILLMENT_ACTIVE;
            }
        }

        return $steps;
    }

    private function recordHistory(
        InstallationRequest $request,
        ?string $from,
        string $to,
        string $field,
        ?User $actor,
        ?string $note,
    ): void {
        InstallationRequestStatusHistory::query()->create([
            'installation_request_id' => $request->id,
            'from_status' => $from,
            'to_status' => $to,
            'field' => $field,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }
}
