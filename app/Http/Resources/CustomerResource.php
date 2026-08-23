<?php

namespace App\Http\Resources;

use App\Models\AccessGrant;
use App\Models\NetworkSession;
use App\Support\DurationFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $grant = $this->resolveCurrentGrant();
        $plan = $grant?->internetPlan;
        $session = $this->resolveLatestSession();
        $mac = $this->resolveMacAddress($session);

        [$timeLeftSeconds, $timeUsedSeconds] = $this->resolveTimers($grant, $session);

        $totalSpent = $this->resolveTotalSpent();
        $currency = $this->resolveCurrency();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'mac_address' => $mac,
            'package' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'badge' => $plan->badge,
                'duration' => $plan->duration,
                'duration_unit' => $plan->duration_unit,
                'price' => $plan->price,
                'status' => $grant?->status,
            ] : null,
            'access_grant' => $grant ? [
                'id' => $grant->id,
                'status' => $grant->status,
                'source' => $grant->source,
                'starts_at' => $grant->starts_at,
                'expires_at' => $grant->expires_at,
            ] : null,
            'time_left_seconds' => $timeLeftSeconds,
            'time_left' => DurationFormatter::format($timeLeftSeconds),
            'time_used_seconds' => $timeUsedSeconds,
            'time_used' => DurationFormatter::format($timeUsedSeconds),
            'total_spent' => $totalSpent,
            'currency' => $currency,
            'total_spent_label' => $currency.' '.number_format((float) $totalSpent, 0, '.', ','),
            'current_session' => $session ? [
                'id' => $session->id,
                'session_id' => $session->session_id,
                'status' => $session->status,
                'mac_address' => $session->mac_address,
                'ip_address' => $session->ip_address,
                'started_at' => $session->started_at,
                'last_activity_at' => $session->last_activity_at,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }

    private function resolveCurrentGrant(): ?AccessGrant
    {
        if ($this->relationLoaded('currentAccessGrant')) {
            return $this->currentAccessGrant;
        }

        if ($this->relationLoaded('accessGrants')) {
            return $this->accessGrants
                ->where('status', 'active')
                ->sortByDesc('id')
                ->first();
        }

        return null;
    }

    private function resolveLatestSession(): ?NetworkSession
    {
        if ($this->relationLoaded('latestNetworkSession')) {
            return $this->latestNetworkSession;
        }

        if ($this->relationLoaded('networkSessions')) {
            return $this->networkSessions->sortByDesc('id')->first();
        }

        return null;
    }

    private function resolveMacAddress(?NetworkSession $session): ?string
    {
        if ($session?->mac_address) {
            return $session->mac_address;
        }

        if ($this->relationLoaded('latestDevice') && $this->latestDevice) {
            return $this->latestDevice->mac_address;
        }

        if ($this->relationLoaded('devices')) {
            return $this->devices->sortByDesc(fn ($device) => $device->last_seen_at ?? $device->id)->first()?->mac_address;
        }

        return null;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveTimers(?AccessGrant $grant, ?NetworkSession $session): array
    {
        if (! $grant) {
            return [null, null];
        }

        $now = now();
        $timeLeftSeconds = null;
        $timeUsedSeconds = null;

        if ($grant->expires_at) {
            $timeLeftSeconds = max(0, $grant->expires_at->getTimestamp() - $now->getTimestamp());
        }

        if ($grant->starts_at) {
            $end = $grant->expires_at && $grant->expires_at->lessThan($now)
                ? $grant->expires_at
                : $now;
            $timeUsedSeconds = max(0, $end->getTimestamp() - $grant->starts_at->getTimestamp());
        } elseif ($session?->started_at) {
            $end = $session->ended_at ?? $session->last_activity_at ?? $now;
            $timeUsedSeconds = max(0, $end->getTimestamp() - $session->started_at->getTimestamp());
        }

        return [$timeLeftSeconds, $timeUsedSeconds];
    }

    private function resolveTotalSpent(): float
    {
        if (isset($this->total_spent)) {
            return round((float) $this->total_spent, 2);
        }

        if ($this->relationLoaded('paymentTransactions')) {
            return round((float) $this->paymentTransactions
                ->where('status', 'paid')
                ->sum('amount'), 2);
        }

        return 0.0;
    }

    private function resolveCurrency(): string
    {
        if ($this->relationLoaded('paymentTransactions')) {
            $currency = $this->paymentTransactions
                ->where('status', 'paid')
                ->sortByDesc('id')
                ->first()
                ?->currency;

            if ($currency) {
                return $currency;
            }
        }

        return 'TZS';
    }
}
