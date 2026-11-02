<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class PackageUnlimitedTest extends TestCase
{
    /**
     * @return array{0: User, 1: Company, 2: array<string, string>}
     */
    private function createOwner(): array
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        return [$owner, $company, $this->authHeaders($owner)];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPackage(array $headers, array $overrides = []): int
    {
        return (int) $this->withHeaders($headers)->postJson('/api/v1/packages', array_merge([
            'name' => 'Always Online',
            'price' => 5000,
            'duration_unit' => 'UNLIMITED_DATA',
        ], $overrides))->assertCreated()->json('data.id');
    }

    /**
     * Buy the package and open a hotspot session through the public API.
     */
    private function buyPackageAndStartSession(array $headers, int $packageId): void
    {
        $paymentId = $this->withHeaders($headers)->postJson('/api/v1/payments', [
            'internet_plan_id' => $packageId,
            'customer_name' => 'Walk in',
            'customer_phone' => '0754123456',
        ])->assertCreated()->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/sessions', [
            'payment_transaction_id' => $paymentId,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])->assertCreated();
    }

    public function test_unlimited_package_is_created_without_a_duration(): void
    {
        [, $company, $headers] = $this->createOwner();

        $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => 'Unlimited',
            'price' => 10000,
            'duration_unit' => 'UNLIMITED_DATA',
        ])->assertCreated()
            ->assertJsonPath('data.duration_unit', 'UNLIMITED_DATA')
            ->assertJsonPath('data.duration', null);

        $this->assertDatabaseHas('internet_plans', [
            'company_id' => $company->id,
            'duration_unit' => 'UNLIMITED_DATA',
            'duration' => null,
        ]);
    }

    public function test_unlimited_package_rejects_a_duration_value(): void
    {
        [, , $headers] = $this->createOwner();

        $this->withHeaders($headers)->postJson('/api/v1/packages', [
            'name' => 'Unlimited',
            'price' => 10000,
            'duration' => 5,
            'duration_unit' => 'UNLIMITED_DATA',
        ])->assertStatus(422)
            ->assertJsonPath('data.duration.0', 'Duration is not used when duration_unit is UNLIMITED_DATA.');
    }

    public function test_unlimited_package_access_never_expires(): void
    {
        [, $company, $headers] = $this->createOwner();
        $packageId = $this->createPackage($headers);

        $this->buyPackageAndStartSession($headers, $packageId);

        $grant = AccessGrant::query()->where('company_id', $company->id)->firstOrFail();

        // No expiry means the customer stays online for as long as the grant is usable.
        $this->assertNull($grant->expires_at);
        $this->assertDatabaseHas('network_sessions', [
            'company_id' => $company->id,
            'access_grant_id' => $grant->id,
            'status' => 'active',
        ]);
    }

    public function test_finite_package_access_still_expires(): void
    {
        [, $company, $headers] = $this->createOwner();
        $packageId = $this->createPackage($headers, [
            'name' => 'One Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
        ]);

        $this->buyPackageAndStartSession($headers, $packageId);

        $grant = AccessGrant::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertNotNull($grant->expires_at);
    }
}
