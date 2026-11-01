<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\InternetPlan;
use App\Models\NetworkDevice;
use App\Models\PaymentTransaction;
use App\Models\PlatformPayment;
use App\Models\User;
use App\Models\Voucher;
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

    public function test_voucher_redemption_expires_the_customers_pending_payment(): void
    {
        $plan = $this->plan('masaa 6', 500, 6, 'HOURS');

        $customer = Customer::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Pending Customer',
            'phone' => '0712345678',
            'status' => 'active',
        ]);

        $platform = PlatformPayment::query()->create([
            'company_id' => $this->company->id,
            'type' => PlatformPayment::TYPE_HOTSPOT_PORTAL,
            'purpose' => PlatformPayment::PURPOSE_HOTSPOT_PORTAL,
            'direction' => PlatformPayment::DIRECTION_COLLECTION,
            'reference' => 'HOT-'.Str::upper(Str::random(10)),
            'amount' => 500,
            'currency' => 'TZS',
            'status' => PlatformPayment::STATUS_PENDING,
            'initiated_at' => now(),
        ]);

        $pending = PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => 500,
            'currency' => 'TZS',
            'payment_method' => 'Mpesa',
            'status' => 'pending',
            'initiated_at' => now(),
            'metadata' => ['platform_payment_id' => $platform->id],
        ]);

        $paid = PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'internet_plan_id' => $plan->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => 500,
            'currency' => 'TZS',
            'payment_method' => 'Mpesa',
            'status' => 'paid',
            'initiated_at' => now(),
            'paid_at' => now(),
        ]);

        $code = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $plan->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->json('data.items.0.code');

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'Pending Customer',
            'customer_phone' => '0712345678',
        ])->assertCreated();

        $this->assertSame('expired', $pending->fresh()->status);
        $this->assertSame(PlatformPayment::STATUS_EXPIRED, $platform->fresh()->status);
        $this->assertSame('paid', $paid->fresh()->status);
    }

    public function test_expire_pending_payments_command_expires_stale_attempts_only(): void
    {
        $stale = PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => 500,
            'currency' => 'TZS',
            'payment_method' => 'Mpesa',
            'status' => 'pending',
            'initiated_at' => now()->subHours(2),
        ]);
        $stale->forceFill(['created_at' => now()->subHours(2)])->save();

        $recent = PaymentTransaction::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'amount' => 500,
            'currency' => 'TZS',
            'payment_method' => 'Mpesa',
            'status' => 'pending',
            'initiated_at' => now(),
        ]);

        $oldPortal = PlatformPayment::query()->create([
            'company_id' => $this->company->id,
            'type' => PlatformPayment::TYPE_HOTSPOT_PORTAL,
            'purpose' => PlatformPayment::PURPOSE_HOTSPOT_PORTAL,
            'direction' => PlatformPayment::DIRECTION_COLLECTION,
            'reference' => 'HOT-'.Str::upper(Str::random(10)),
            'amount' => 500,
            'currency' => 'TZS',
            'status' => PlatformPayment::STATUS_PENDING,
            'initiated_at' => now()->subHours(2),
        ]);
        $oldPortal->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->artisan('payments:expire-pending')->assertSuccessful();

        $this->assertSame('expired', $stale->fresh()->status);
        $this->assertSame('pending', $recent->fresh()->status);
        $this->assertSame(PlatformPayment::STATUS_EXPIRED, $oldPortal->fresh()->status);
    }

    public function test_selected_vouchers_can_be_downloaded_as_a_word_document(): void
    {
        $plan = $this->plan('masaa 6', 500, 6, 'HOURS');

        $items = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $plan->id,
                'quantity' => 2,
                'note' => 'Cash sale',
            ])
            ->assertCreated()
            ->json('data.items');

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers/export/word', [
                'ids' => array_column($items, 'id'),
            ])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->assertSame('PK', substr((string) $response->getContent(), 0, 2));

        $document = $this->documentXml($response);

        foreach ($items as $item) {
            $this->assertStringContainsString((string) $item['code'], $document);
        }

        $this->assertStringContainsString('masaa 6', $document);
        $this->assertStringContainsString('Cash sale', $document);
    }

    public function test_word_export_can_filter_by_package(): void
    {
        $sixHours = $this->plan('masaa 6', 500, 6, 'HOURS');
        $oneDay = $this->plan('masaa 24', 1000, 24, 'HOURS');

        $sixHourCode = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $sixHours->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->json('data.items.0.code');

        $oneDayCode = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $oneDay->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->json('data.items.0.code');

        $document = $this->documentXml(
            $this->withHeaders($this->headers)
                ->postJson('/api/v1/vouchers/export/word', ['package_id' => $sixHours->id])
                ->assertOk(),
        );

        $this->assertStringContainsString((string) $sixHourCode, $document);
        $this->assertStringNotContainsString((string) $oneDayCode, $document);
    }

    public function test_word_export_returns_not_found_for_unknown_vouchers(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers/export/word', ['ids' => [999999]])
            ->assertStatus(404);
    }

    public function test_new_vouchers_are_single_use(): void
    {
        $plan = $this->plan('masaa 6', 500, 6, 'HOURS');

        $item = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $plan->id,
                'quantity' => 1,
                'max_uses' => 5,
            ])
            ->assertCreated()
            ->json('data.items.0');

        $this->assertSame(1, $item['max_uses']);
        $this->assertFalse($item['is_used']);
        $this->assertSame(1, $item['remaining_uses']);
    }

    public function test_a_voucher_cannot_be_redeemed_twice(): void
    {
        $plan = $this->plan('masaa 6', 500, 6, 'HOURS');

        $code = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $plan->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->json('data.items.0.code');

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'First Customer',
            'customer_phone' => '0712345678',
        ])->assertCreated();

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'First Customer',
            'customer_phone' => '0712345678',
        ])->assertStatus(422)
            ->assertJsonPath('data.voucher.0', 'This voucher has no remaining uses.');

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'Someone Else',
            'customer_phone' => '0755555555',
        ])->assertStatus(422);
    }

    public function test_legacy_multi_use_voucher_cannot_be_reused_by_the_same_customer(): void
    {
        $plan = $this->plan('masaa 6', 500, 6, 'HOURS');
        $code = '555111';

        Voucher::query()->create([
            'company_id' => $this->company->id,
            'network_device_id' => $this->router->id,
            'internet_plan_id' => $plan->id,
            'code' => $code,
            'max_uses' => 3,
            'uses_count' => 0,
            'status' => Voucher::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'Same Customer',
            'customer_phone' => '0712345678',
        ])->assertCreated();

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'Same Customer',
            'customer_phone' => '0712345678',
        ])->assertStatus(422)
            ->assertJsonPath('data.code.0', 'This voucher was already used by this customer.');

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $code,
            'customer_name' => 'Different Customer',
            'customer_phone' => '0755555555',
        ])->assertCreated();
    }

    public function test_voucher_list_can_filter_used_and_unused(): void
    {
        $plan = $this->plan('masaa 6', 500, 6, 'HOURS');

        $items = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $plan->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->json('data.items');

        $usedCode = $items[0]['code'];
        $unusedCode = $items[1]['code'];

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $usedCode,
            'customer_name' => 'List Customer',
            'customer_phone' => '0712345678',
        ])->assertCreated();

        $used = $this->withHeaders($this->headers)
            ->getJson('/api/v1/vouchers?usage=used')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.code', $usedCode)
            ->assertJsonPath('data.items.0.is_used', true);

        $this->assertSame(1, $used->json('data.items.0.uses_count'));

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/vouchers?usage=unused')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.code', $unusedCode)
            ->assertJsonPath('data.items.0.is_used', false);
    }

    public function test_voucher_summary_counts_used_unused_and_available(): void
    {
        $plan = $this->plan('masaa 6', 500, 6, 'HOURS');

        $items = $this->withHeaders($this->headers)
            ->postJson('/api/v1/vouchers', [
                'router_id' => $this->router->id,
                'package_id' => $plan->id,
                'quantity' => 3,
            ])
            ->assertCreated()
            ->json('data.items');

        $this->postJson('/api/v1/portal/voucher-cafe/vouchers/redeem', [
            'code' => $items[0]['code'],
            'customer_name' => 'Summary Customer',
            'customer_phone' => '0712345678',
        ])->assertCreated();

        $this->withHeaders($this->headers)
            ->postJson("/api/v1/vouchers/{$items[1]['id']}/revoke")
            ->assertOk();

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/vouchers/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.used', 1)
            ->assertJsonPath('data.unused', 2)
            ->assertJsonPath('data.available', 1)
            ->assertJsonPath('data.expired', 0)
            ->assertJsonPath('data.revoked', 1);

        $this->assertEqualsWithDelta(500, $response->json('data.sold_value'), 0.01);
    }

    private function documentXml(\Illuminate\Testing\TestResponse $response): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'voucher-docx-');
        file_put_contents($path, (string) $response->getContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $document = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        $this->assertNotFalse(simplexml_load_string($document));

        return $document;
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
