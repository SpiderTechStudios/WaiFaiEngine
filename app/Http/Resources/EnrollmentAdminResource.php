<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use App\Models\PlatformPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->resource;

        $payments = $enrollment->relationLoaded('payments')
            ? $enrollment->payments->sortByDesc('id')->values()
            : $enrollment->payments()->latest('id')->get();

        $latest = $payments->first();

        return [
            'id' => $enrollment->id,
            'reference' => $enrollment->reference,
            'status' => $enrollment->status,
            'business_name' => $enrollment->business_name,
            'first_name' => $enrollment->first_name,
            'last_name' => $enrollment->last_name,
            'email' => $enrollment->email,
            'phone' => $enrollment->phone,
            'payment_phone' => $enrollment->payment_phone,
            'portal_subdomain' => $enrollment->portal_subdomain,
            'amount' => $enrollment->total_amount,
            'currency' => $enrollment->currency,
            'failed_payment_attempts' => $enrollment->failed_payment_attempts,
            'remaining_attempts' => $enrollment->remainingAttempts(),
            'expires_at' => optional($enrollment->expires_at)?->toIso8601String(),
            'is_expired' => $enrollment->isExpired(),
            'created_at' => optional($enrollment->created_at)?->toIso8601String(),
            'payment_status' => $latest?->status,
            'latest_payment' => $latest ? $this->payment($latest) : null,
            'payments' => $payments
                ->map(fn (PlatformPayment $payment) => $this->payment($payment))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(PlatformPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'status' => $payment->status,
            'purpose' => $payment->resolvePurpose(),
            'provider' => $payment->provider_slug,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'external_reference' => $payment->external_reference,
            'phone' => $payment->phone,
            'initiated_at' => optional($payment->initiated_at)?->toIso8601String(),
            'paid_at' => optional($payment->paid_at)?->toIso8601String(),
            'failed_at' => optional($payment->failed_at)?->toIso8601String(),
            'failure_reason' => data_get($payment->metadata, 'failure_reason'),
            'provider_message' => data_get($payment->metadata, 'provider_charge.message'),
            'requires_manual_review' => (bool) data_get($payment->metadata, 'requires_manual_review', false),
        ];
    }
}
