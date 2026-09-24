<?php

namespace App\Services;

use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\Offer;
use App\Models\OfferClaim;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * The best currently-claimable offer for a company (optionally scoped to a
     * router and checked against a phone+mac that may have already claimed).
     *
     * @return array<string, mixed>|null
     */
    public function activeFor(Company $company, ?int $routerId = null, ?string $phone = null, ?string $mac = null): ?array
    {
        $offer = Offer::query()
            ->with('routers')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('ends_at')
            ->orderBy('id')
            ->get()
            ->first(fn (Offer $candidate) => $candidate->isWithinWindow()
                && $candidate->hasRemainingClaims()
                && $candidate->appliesToRouter($routerId, $candidate->routers));

        if (! $offer) {
            return null;
        }

        $claimed = false;
        if (filled($phone)) {
            $claimed = OfferClaim::query()
                ->where('offer_id', $offer->id)
                ->where('customer_phone', $this->normalizePhone((string) $phone))
                ->where('device_mac', $this->normalizeMac($mac))
                ->exists();
        }

        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'description' => $offer->description,
            'duration' => $offer->duration,
            'duration_unit' => $offer->duration_unit,
            'remaining_claims' => $offer->remainingClaims(),
            'claimed' => $claimed,
        ];
    }

    /**
     * Claim an offer: grant free access for the offer duration, starting now.
     * Never records revenue - offer access is free.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function claim(Company $company, Offer $offer, array $data): array
    {
        return DB::transaction(function () use ($company, $offer, $data) {
            $offer = Offer::query()
                ->where('company_id', $company->id)
                ->whereKey($offer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $offer->is_active) {
                throw ValidationException::withMessages([
                    'offer' => ['This offer is not active.'],
                ]);
            }

            if (! $offer->isWithinWindow()) {
                throw ValidationException::withMessages([
                    'offer' => ['This offer is not available right now.'],
                ]);
            }

            if (! $offer->hasRemainingClaims()) {
                throw ValidationException::withMessages([
                    'offer' => ['This offer has already been fully claimed.'],
                ]);
            }

            $phone = $this->normalizePhone((string) $data['customer_phone']);
            $mac = $this->normalizeMac($data['mac_address'] ?? null);

            $alreadyClaimed = OfferClaim::query()
                ->where('offer_id', $offer->id)
                ->where('customer_phone', $phone)
                ->where('device_mac', $mac)
                ->exists();

            if ($alreadyClaimed) {
                throw ValidationException::withMessages([
                    'offer' => ['You have already claimed this offer.'],
                ]);
            }

            $customer = $this->resolveCustomer($company->id, $data, $phone);
            $plan = $this->resolveGrantPlan($offer);

            $startsAt = now();
            $expiresAt = $this->grantExpiresAt($offer, $plan, $startsAt);

            $grant = AccessGrant::query()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'internet_plan_id' => $plan->id,
                'offer_id' => $offer->id,
                'source' => 'offer',
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            $claim = OfferClaim::query()->create([
                'company_id' => $company->id,
                'offer_id' => $offer->id,
                'customer_id' => $customer->id,
                'access_grant_id' => $grant->id,
                'captive_session_id' => $data['captive_session_id'] ?? null,
                'customer_phone' => $phone,
                'device_mac' => $mac,
                'claimed_at' => $startsAt,
                'expires_at' => $expiresAt,
            ]);

            $offer->forceFill(['claims_count' => $offer->claims_count + 1])->save();

            $this->auditLogger->log(
                'offer_claimed',
                null,
                $company->id,
                Offer::class,
                $offer->id,
                newValues: [
                    'customer_id' => $customer->id,
                    'access_grant_id' => $grant->id,
                    'customer_phone' => $phone,
                    'device_mac' => $mac,
                ],
            );

            return [
                'offer' => $offer->fresh(),
                'offer_claim' => $claim,
                'access_grant' => $grant,
                'customer' => $customer,
                'package' => $plan,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): Offer
    {
        return DB::transaction(function () use ($company, $data, $actor) {
            $offer = Offer::query()->create([
                'company_id' => $company->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'duration' => $data['duration'] ?? null,
                'duration_unit' => $data['duration_unit'] ?? null,
                'max_claims' => $data['max_claims'] ?? null,
                'claims_count' => 0,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'internet_plan_id' => $data['internet_plan_id'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->syncRouters($offer, $company, $data['router_ids'] ?? []);

            $this->auditLogger->log(
                'offer_created',
                $actor,
                $company->id,
                Offer::class,
                $offer->id,
                newValues: ['title' => $offer->title],
            );

            return $offer->fresh(['internetPlan', 'routers']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Offer $offer, array $data, User $actor): Offer
    {
        return DB::transaction(function () use ($offer, $data, $actor) {
            $offer->fill([
                'title' => $data['title'] ?? $offer->title,
                'description' => array_key_exists('description', $data) ? $data['description'] : $offer->description,
                'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $offer->starts_at,
                'ends_at' => array_key_exists('ends_at', $data) ? $data['ends_at'] : $offer->ends_at,
                'duration' => array_key_exists('duration', $data) ? $data['duration'] : $offer->duration,
                'duration_unit' => array_key_exists('duration_unit', $data) ? $data['duration_unit'] : $offer->duration_unit,
                'max_claims' => array_key_exists('max_claims', $data) ? $data['max_claims'] : $offer->max_claims,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $offer->is_active,
                'internet_plan_id' => array_key_exists('internet_plan_id', $data) ? $data['internet_plan_id'] : $offer->internet_plan_id,
            ])->save();

            if (array_key_exists('router_ids', $data)) {
                $this->syncRouters($offer, $offer->company, $data['router_ids'] ?? []);
            }

            $this->auditLogger->log('offer_updated', $actor, $offer->company_id, Offer::class, $offer->id);

            return $offer->fresh(['internetPlan', 'routers']);
        });
    }

    public function delete(Offer $offer, User $actor): void
    {
        // Claims / grants reference the offer with restrictOnDelete, so an
        // offer that was already used is deactivated instead of removed.
        if ($offer->claims()->exists()) {
            $offer->forceFill(['is_active' => false])->save();
            $this->auditLogger->log('offer_deactivated', $actor, $offer->company_id, Offer::class, $offer->id);

            return;
        }

        $offer->delete();
        $this->auditLogger->log('offer_deleted', $actor, $offer->company_id, Offer::class, $offer->id);
    }

    public function activate(Offer $offer, User $actor): Offer
    {
        $offer->forceFill(['is_active' => true])->save();
        $this->auditLogger->log('offer_activated', $actor, $offer->company_id, Offer::class, $offer->id);

        return $offer->fresh(['internetPlan', 'routers']);
    }

    public function deactivate(Offer $offer, User $actor): Offer
    {
        $offer->forceFill(['is_active' => false])->save();
        $this->auditLogger->log('offer_deactivated', $actor, $offer->company_id, Offer::class, $offer->id);

        return $offer->fresh(['internetPlan', 'routers']);
    }

    /**
     * @param  list<int|string>  $routerIds
     */
    private function syncRouters(Offer $offer, Company $company, array $routerIds): void
    {
        $ids = NetworkDevice::query()
            ->where('company_id', $company->id)
            ->where('type', 'router')
            ->whereIn('id', $routerIds)
            ->pluck('id')
            ->all();

        $offer->routers()->sync($ids);
    }

    private function resolveGrantPlan(Offer $offer): InternetPlan
    {
        if ($offer->internet_plan_id) {
            $plan = InternetPlan::query()
                ->where('company_id', $offer->company_id)
                ->find($offer->internet_plan_id);

            if ($plan) {
                return $plan;
            }
        }

        return $this->hiddenPlanFor($offer);
    }

    /**
     * A zero-price, inactive plan that backs standalone offers so the grant has
     * a valid internet_plan_id without exposing a package in the portal.
     */
    private function hiddenPlanFor(Offer $offer): InternetPlan
    {
        $slug = 'offer-'.$offer->id;

        $attributes = [
            'name' => $offer->title ?: 'Free offer',
            'badge' => 'OFFER',
            'description' => $offer->description,
            'duration' => $offer->duration,
            'duration_unit' => $offer->duration_unit ?: 'HOURS',
            'price' => 0,
            'status' => 'inactive',
        ];

        $plan = InternetPlan::withTrashed()
            ->where('company_id', $offer->company_id)
            ->where('slug', $slug)
            ->first();

        if ($plan) {
            if ($plan->trashed()) {
                $plan->restore();
            }

            $plan->forceFill($attributes)->save();

            return $plan;
        }

        return InternetPlan::query()->create([
            'company_id' => $offer->company_id,
            'slug' => $slug,
            ...$attributes,
        ]);
    }

    private function grantExpiresAt(Offer $offer, InternetPlan $plan, Carbon $start): ?Carbon
    {
        $duration = $offer->duration ?? $plan->duration;
        $unit = $offer->duration_unit ?? $plan->duration_unit;

        if ($duration === null || $unit === null || $unit === 'UNLIMITED_DATA') {
            return null;
        }

        return match ($unit) {
            'HOURS' => $start->copy()->addHours((int) $duration),
            'DAYS' => $start->copy()->addDays((int) $duration),
            'WEEKS' => $start->copy()->addWeeks((int) $duration),
            'MONTHS' => $start->copy()->addMonths((int) $duration),
            default => $start->copy()->addHours((int) $duration),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(int $companyId, array $data, string $phone): Customer
    {
        $raw = (string) ($data['customer_phone'] ?? '');

        $existing = Customer::query()
            ->where('company_id', $companyId)
            ->whereIn('phone', array_values(array_unique(array_filter([$phone, $raw]))))
            ->first();

        if ($existing) {
            if (filled($data['customer_name'] ?? null) && $existing->name !== $data['customer_name']) {
                $existing->forceFill(['name' => $data['customer_name']])->save();
            }

            return $existing;
        }

        return Customer::query()->create([
            'company_id' => $companyId,
            'name' => $data['customer_name'] ?? 'Offer guest',
            'phone' => $phone,
            'email' => $data['customer_email'] ?? null,
            'status' => 'active',
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return $digits;
        }

        if (strlen($digits) === 9) {
            return '0'.$digits;
        }

        return $digits !== '' ? $digits : $phone;
    }

    private function normalizeMac(?string $mac): string
    {
        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', (string) $mac) ?? '');

        if (strlen($clean) !== 12) {
            return OfferClaim::UNKNOWN_MAC;
        }

        return implode(':', str_split($clean, 2));
    }
}
