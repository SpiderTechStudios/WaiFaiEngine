<?php

namespace App\Services;

use App\Http\Resources\PackageResource;
use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkSession;
use Illuminate\Validation\ValidationException;

class PortalService
{
    /**
     * @return array<string, mixed>
     */
    public function bootstrap(Company $company): array
    {
        $packages = InternetPlan::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('price')
            ->get();

        return [
            'company' => [
                'name' => $company->name,
                'subdomain' => $company->subdomain,
                'primary_color' => $company->primary_color,
                'logo_url' => $company->logo_url,
                'captive_portal_welcome_message' => $company->captive_portal_welcome_message,
                'payment_method' => $company->payment_method,
            ],
            'packages' => PackageResource::collection($packages)->resolve(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function restore(Company $company, array $data): array
    {
        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->where('phone', $data['customer_phone'])
            ->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_phone' => ['No active access found for this phone number.'],
            ]);
        }

        $grant = AccessGrant::query()
            ->with('internetPlan')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if (! $grant) {
            throw ValidationException::withMessages([
                'customer_phone' => ['No active access found for this phone number.'],
            ]);
        }

        $session = null;
        if (! empty($data['mac_address'])) {
            $mac = $this->normalizeMac((string) $data['mac_address']);
            $session = NetworkSession::query()
                ->where('company_id', $company->id)
                ->where('customer_id', $customer->id)
                ->where('mac_address', $mac)
                ->where('status', 'active')
                ->latest('id')
                ->first();
        }

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ],
            'access_grant' => $this->formatAccessGrant($grant),
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'mac_address' => $session->mac_address,
                'access_grant_id' => $session->access_grant_id,
                'started_at' => $session->started_at,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAccessGrant(AccessGrant $grant): array
    {
        return [
            'id' => $grant->id,
            'status' => $grant->status,
            'source' => $grant->source,
            'starts_at' => $grant->starts_at,
            'expires_at' => $grant->expires_at,
            'package' => $grant->internetPlan ? [
                'id' => $grant->internetPlan->id,
                'name' => $grant->internetPlan->name,
                'duration' => $grant->internetPlan->duration,
                'duration_unit' => $grant->internetPlan->duration_unit,
            ] : null,
        ];
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
}
