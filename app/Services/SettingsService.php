<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class SettingsService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function show(Company $company): array
    {
        $settings = array_merge($this->defaults(), $company->settings ?? []);

        return [
            'id' => $company->id,
            'name' => $company->name,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'timezone' => $company->timezone,
            'subdomain' => $company->subdomain,
            'portal_url' => $company->subdomain
                ? url('/connect?subdomain='.$company->subdomain)
                : null,
            'status' => $company->status,
            'primary_color' => $settings['primary_color'] ?? null,
            'logo_url' => $settings['logo_url'] ?? null,
            'voucher_code_digits' => (int) ($settings['voucher_code_digits'] ?? 6),
            'payout_methods' => $settings['payout_methods'] ?? [],
            'captive_portal_welcome_message' => $settings['captive_portal_welcome_message'] ?? null,
            'ruijie_account_id' => $settings['ruijie_account_id'] ?? null,
            'ruijie_password_set' => filled($settings['ruijie_password'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(Company $company, array $data, User $actor): array
    {
        $settings = array_merge($this->defaults(), $company->settings ?? []);

        foreach ([
            'primary_color',
            'logo_url',
            'voucher_code_digits',
            'payout_methods',
            'captive_portal_welcome_message',
            'ruijie_account_id',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = $data[$key];
            }
        }

        if (array_key_exists('ruijie_password', $data) && filled($data['ruijie_password'])) {
            $settings['ruijie_password'] = Crypt::encryptString($data['ruijie_password']);
        }

        $company->fill(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'timezone' => $data['timezone'] ?? null,
        ], fn ($value) => $value !== null));

        if (array_key_exists('portal_subdomain', $data) && filled($data['portal_subdomain'])) {
            $company->subdomain = $this->uniqueSubdomain($data['portal_subdomain'], $company->id);
        }

        $company->settings = $settings;
        $company->save();

        $this->auditLogger->log('settings_updated', $actor, $company->id, Company::class, $company->id);

        return $this->show($company->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'primary_color' => '#0F4C81',
            'logo_url' => null,
            'voucher_code_digits' => 6,
            'payout_methods' => [],
            'captive_portal_welcome_message' => 'Welcome to WiFi. Choose a package or enter a voucher code.',
            'ruijie_account_id' => null,
            'ruijie_password' => null,
        ];
    }

    private function uniqueSubdomain(string $value, int $ignoreId): string
    {
        $base = Str::slug($value) ?: 'wifi';
        $subdomain = $base;
        $i = 1;

        while (Company::query()->where('subdomain', $subdomain)->where('id', '!=', $ignoreId)->exists()) {
            $subdomain = $base.'-'.$i++;
        }

        return $subdomain;
    }
}
