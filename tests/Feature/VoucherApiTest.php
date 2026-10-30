<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\User;
use App\Services\RouterService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoucherApiTest extends TestCase
{
    private User $owner;

    private Company $company;

    /** @var array<string, string> */
    private array $headers;

    private NetworkDevice $router;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-30 09:00:00', 'UTC'));

        $this->owner = $this->createUser();
        $this->company = $this->createCompanyFor($this->owner, 'owner', [
            'subdomain' => 'voucher-cafe',
            'payment_method' => 'both',
        ]);
        $this->headers = $this->authHeaders($this->owner);
        $this->router = app(RouterService::class)->create($this->company, [
            'gateway_type' => 'mikrotik',
            'name' => 'Voucher AP',
            'lan_ip' => '192.168.88.1',
            'api_username' => 'admin',
            'api_password' => 'secret',
        ], $this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_voucher_cannot_use_an_inactive_package(): void
    {
        $inactive = $this->plan('masaa 6', 500, 6, 'HOURS', InternetPlan::STATUS_INACTIVE);

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $inactive->id,
                'quantity' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.package_id.0', 'This package is inactive. Activate it or choose another package.');
    }

    public function test_a_500_voucher_grants_its_packages_duration_not_the_amount(): void
    {
        // In this account 500 = 6 hours (duration is set per account/package).
        $plan = $this->plan('masaa 6', 500, 6, 'HOURS');
        $other = $this->plan('masaa 24', 1000, 24, 'HOURS');

        $code = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $plan->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.package.id', $plan->id)
            ->assertJsonPath('data.items.0.package.duration', 6)
            ->assertJsonPath('data.items.0.package.duration_unit', 'HOURS')
            ->json('data.items.0.code');

        $redemption = $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'Test Customer',
            'customer_phone' => '0712345678',
        ])->assertCreated();

        $redemption->assertJsonPath('data.package.id', $plan->id)
            ->assertJsonPath('data.package.name', 'masaa 6')
            ->assertJsonPath('data.package.duration', 6)
            ->assertJsonPath('data.package.duration_unit', 'HOURS');

        $expiresAt = Carbon::parse((string) $redemption->json('data.access_grant.expires_at'));

        $this->assertTrue($expiresAt->equalTo(now()->addHours(6)));
        $this->assertNotSame($other->id, $redemption->json('data.package.id'));
    }

    private function plan(
        string $name,
        float $price,
        int $duration,
        string $unit,
        string $status = InternetPlan::STATUS_ACTIVE,
    ): InternetPlan {
        return InternetPlan::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'duration' => $duration,
            'duration_unit' => $unit,
            'price' => $price,
            'status' => $status,
        ]);
    }
}
