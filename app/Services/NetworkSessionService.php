<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\CustomerDevice;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\NetworkSession;
use App\Models\PaymentTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NetworkSessionService
{
    /** @var list<string> */
    private const SESSION_RELATIONS = [
        'customer',
        'networkDevice',
        'accessGrant',
        'internetPlan',
        'paymentTransaction',
    ];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, ?User $actor = null): NetworkSession
    {
        return DB::transaction(function () use ($company, $data, $actor) {
            $grant = $this->resolveAccessGrant($company, $data);
            $this->assertGrantUsable($grant);

            if (! $grant->internet_plan_id) {
                throw ValidationException::withMessages([
                    'access_grant_id' => ['Access grant is not linked to a package.'],
                ]);
            }

            $mac = isset($data['mac_address']) ? $this->normalizeMac((string) $data['mac_address']) : null;
            $device = $mac ? $this->findOrCreateDevice($company, $grant->customer_id, $mac) : null;

            $routerId = $data['router_id'] ?? $data['network_device_id'] ?? null;
            if ($routerId) {
                $routerExists = NetworkDevice::query()
                    ->where('company_id', $company->id)
                    ->where('type', 'router')
                    ->whereKey($routerId)
                    ->exists();

                if (! $routerExists) {
                    $deleted = NetworkDevice::onlyTrashed()
                        ->where('company_id', $company->id)
                        ->where('type', 'router')
                        ->whereKey($routerId)
                        ->exists();

                    throw ValidationException::withMessages([
                        'router_id' => [
                            $deleted
                                ? 'This router has been deleted. Create a new router or restore it before starting a session.'
                                : 'Router not found for this company.',
                        ],
                    ]);
                }
            }

            $externalSessionId = $data['session_id'] ?? null;
            if (filled($externalSessionId)) {
                $existing = NetworkSession::query()
                    ->where('company_id', $company->id)
                    ->where('session_id', $externalSessionId)
                    ->where('status', 'active')
                    ->first();

                if ($existing) {
                    $existing->forceFill([
                        'last_activity_at' => now(),
                        'ip_address' => $data['ip_address'] ?? $existing->ip_address,
                        'mac_address' => $mac ?? $existing->mac_address,
                        'customer_device_id' => $device?->id ?? $existing->customer_device_id,
                        'network_device_id' => $routerId ?? $existing->network_device_id,
                        'internet_plan_id' => $grant->internet_plan_id,
                        'payment_transaction_id' => $grant->payment_transaction_id,
                        'access_grant_id' => $grant->id,
                    ])->save();

                    return $existing->fresh()->load(self::SESSION_RELATIONS);
                }
            }

            if ($mac) {
                $activeForMac = NetworkSession::query()
                    ->where('company_id', $company->id)
                    ->where('mac_address', $mac)
                    ->where('access_grant_id', $grant->id)
                    ->where('status', 'active')
                    ->first();

                if ($activeForMac) {
                    $activeForMac->forceFill([
                        'last_activity_at' => now(),
                        'ip_address' => $data['ip_address'] ?? $activeForMac->ip_address,
                        'session_id' => $externalSessionId ?? $activeForMac->session_id,
                        'network_device_id' => $routerId ?? $activeForMac->network_device_id,
                        'internet_plan_id' => $grant->internet_plan_id,
                        'payment_transaction_id' => $grant->payment_transaction_id,
                    ])->save();

                    return $activeForMac->fresh()->load(self::SESSION_RELATIONS);
                }
            }

            $now = now();
            $session = NetworkSession::query()->create([
                'company_id' => $company->id,
                'customer_id' => $grant->customer_id,
                'customer_device_id' => $device?->id,
                'access_grant_id' => $grant->id,
                'internet_plan_id' => $grant->internet_plan_id,
                'payment_transaction_id' => $grant->payment_transaction_id,
                'network_station_id' => $data['network_station_id'] ?? null,
                'network_device_id' => $routerId,
                'network_ssid_id' => $data['network_ssid_id'] ?? null,
                'session_id' => $externalSessionId,
                'mac_address' => $mac,
                'ip_address' => $data['ip_address'] ?? null,
                'started_at' => $now,
                'last_activity_at' => $now,
                'upload_bytes' => 0,
                'download_bytes' => 0,
                'status' => 'active',
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->auditLogger->log(
                'session_created',
                $actor,
                $company->id,
                NetworkSession::class,
                $session->id,
            );

            return $session->load(self::SESSION_RELATIONS);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveAccessGrant(Company $company, array $data): AccessGrant
    {
        if (! empty($data['access_grant_id'])) {
            return AccessGrant::query()
                ->where('company_id', $company->id)
                ->findOrFail($data['access_grant_id']);
        }

        $payment = PaymentTransaction::query()
            ->with('internetPlan')
            ->where('company_id', $company->id)
            ->findOrFail($data['payment_transaction_id']);

        if ($payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'payment_transaction_id' => ['Only paid payments can start a hotspot session.'],
            ]);
        }

        $existing = AccessGrant::query()
            ->where('company_id', $company->id)
            ->where('payment_transaction_id', $payment->id)
            ->whereIn('status', ['active', 'pending'])
            ->latest('id')
            ->first();

        if ($existing) {
            if ($existing->status === 'pending') {
                $existing->forceFill(['status' => 'active'])->save();
            }

            return $existing;
        }

        $plan = $payment->internetPlan;
        if (! $plan) {
            throw ValidationException::withMessages([
                'payment_transaction_id' => ['Payment is not linked to a package.'],
            ]);
        }

        $startsAt = now();

        return AccessGrant::query()->create([
            'company_id' => $company->id,
            'customer_id' => $payment->customer_id,
            'internet_plan_id' => $plan->id,
            'payment_transaction_id' => $payment->id,
            'source' => 'payment',
            'starts_at' => $startsAt,
            'expires_at' => $this->expiresAtForPlan($plan, $startsAt),
            'status' => 'active',
        ]);
    }

    private function assertGrantUsable(AccessGrant $grant): void
    {
        if ($grant->status !== 'active') {
            throw ValidationException::withMessages([
                'access_grant_id' => ['Access grant is not active.'],
            ]);
        }

        if ($grant->expires_at && $grant->expires_at->isPast()) {
            $grant->forceFill(['status' => 'expired'])->save();

            throw ValidationException::withMessages([
                'access_grant_id' => ['Access grant has expired.'],
            ]);
        }
    }

    private function findOrCreateDevice(Company $company, int $customerId, string $mac): CustomerDevice
    {
        $device = CustomerDevice::query()
            ->where('company_id', $company->id)
            ->where('mac_address', $mac)
            ->first();

        if ($device) {
            $device->forceFill([
                'customer_id' => $customerId,
                'last_seen_at' => now(),
                'status' => 'active',
            ])->save();

            return $device;
        }

        return CustomerDevice::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customerId,
            'mac_address' => $mac,
            'status' => 'active',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function normalizeMac(string $mac): string
    {
        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac) ?? '');

        if (strlen($clean) !== 12) {
            throw ValidationException::withMessages([
                'mac_address' => ['MAC address must be 12 hex digits (e.g. AA:BB:CC:DD:EE:FF).'],
            ]);
        }

        return implode(':', str_split($clean, 2));
    }

    private function expiresAtForPlan(InternetPlan $plan, Carbon $startsAt): ?Carbon
    {
        if ($plan->duration_unit === InternetPlan::DURATION_UNITS[4] || blank($plan->duration)) {
            return null;
        }

        return match ($plan->duration_unit) {
            'HOURS' => $startsAt->copy()->addHours((int) $plan->duration),
            'DAYS' => $startsAt->copy()->addDays((int) $plan->duration),
            'WEEKS' => $startsAt->copy()->addWeeks((int) $plan->duration),
            'MONTHS' => $startsAt->copy()->addMonths((int) $plan->duration),
            default => $startsAt->copy()->addHours((int) $plan->duration),
        };
    }
}
