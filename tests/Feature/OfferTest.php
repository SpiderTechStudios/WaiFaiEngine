<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\CaptiveSession;
use App\Models\Company;
use App\Models\NetworkDevice;
use App\Models\Offer;
use App\Models\OfferClaim;
use App\Models\RevenueRecord;
use App\Services\OfferService;
use Tests\TestCase;

class OfferTest extends TestCase
{
    public function test_owner_can_create_and_list_offers(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $this->withHeaders($headers)->postJson('/api/v1/offers', [
            'title' => '3 Hours Free',
            'description' => 'Enjoy 3 hours on us',
            'duration' => 3,
            'duration_unit' => 'HOURS',
            'max_claims' => 5,
        ])->assertCreated()
            ->assertJsonPath('data.title', '3 Hours Free')
            ->assertJsonPath('data.duration', 3)
            ->assertJsonPath('data.duration_unit', 'HOURS')
            ->assertJsonPath('data.max_claims', 5)
            ->assertJsonPath('data.remaining_claims', 5)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('offers', [
            'title' => '3 Hours Free',
            'duration' => 3,
            'duration_unit' => 'HOURS',
            'max_claims' => 5,
            'claims_count' => 0,
        ]);

        $this->withHeaders($headers)->getJson('/api/v1/offers')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.title', '3 Hours Free');
    }

    public function test_owner_can_scope_offer_to_a_router(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $routerId = $this->withHeaders($headers)->postJson('/api/v1/routers', [
            'gateway_type' => 'mikrotik',
            'name' => 'Scoped Router',
            'lan_ip' => '192.168.88.1',
            'api_host' => '41.59.12.34',
            'api_port' => 443,
            'api_username' => 'admin',
            'api_password' => 'secret-pass',
        ])->assertCreated()->json('data.id');

        $offerId = $this->withHeaders($headers)->postJson('/api/v1/offers', [
            'title' => 'Router Offer',
            'duration' => 2,
            'duration_unit' => 'HOURS',
            'router_ids' => [$routerId],
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('offer_network_device', [
            'offer_id' => $offerId,
            'network_device_id' => $routerId,
        ]);

        $offer = Offer::query()->findOrFail($offerId);

        // Scoped offer applies to its router, not to others / company-wide lookups.
        $this->assertTrue($offer->appliesToRouter((int) $routerId));
        $this->assertFalse($offer->appliesToRouter(null));

        $service = app(OfferService::class);
        $this->assertNotNull($service->activeFor($company, (int) $routerId));
        $this->assertNull($service->activeFor($company, null));
    }

    public function test_portal_bootstrap_includes_active_offer(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        Offer::query()->create([
            'company_id' => $company->id,
            'title' => 'Free Hour',
            'description' => 'One free hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'is_active' => true,
            'claims_count' => 0,
        ]);

        $this->getJson('/api/v1/portal/'.$company->subdomain)
            ->assertOk()
            ->assertJsonPath('data.offer.title', 'Free Hour')
            ->assertJsonPath('data.offer.duration', 1);
    }

    public function test_portal_claim_creates_free_grant_without_revenue(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $offer = Offer::query()->create([
            'company_id' => $company->id,
            'title' => '3 Hours Free',
            'duration' => 3,
            'duration_unit' => 'HOURS',
            'max_claims' => 5,
            'is_active' => true,
            'claims_count' => 0,
        ]);

        $this->postJson('/api/v1/portal/'.$company->subdomain.'/offers/claim', [
            'offer_id' => $offer->id,
            'customer_phone' => '0711987654',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])->assertCreated()
            ->assertJsonPath('data.access_grant.source', 'offer')
            ->assertJsonPath('data.offer.remaining_claims', 4);

        $this->assertDatabaseHas('access_grants', [
            'company_id' => $company->id,
            'offer_id' => $offer->id,
            'source' => 'offer',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('offer_claims', [
            'offer_id' => $offer->id,
            'customer_phone' => '0711987654',
            'device_mac' => 'AA:BB:CC:DD:EE:FF',
        ]);

        $this->assertSame(1, $offer->fresh()->claims_count);

        // Free access must not create revenue or wallet credits.
        $this->assertSame(0, RevenueRecord::query()->where('company_id', $company->id)->count());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_claim_blocks_repeat_phone_and_mac(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $offer = Offer::query()->create([
            'company_id' => $company->id,
            'title' => 'Free Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'is_active' => true,
            'claims_count' => 0,
        ]);

        $payload = [
            'offer_id' => $offer->id,
            'customer_phone' => '0711987654',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ];

        $this->postJson('/api/v1/portal/'.$company->subdomain.'/offers/claim', $payload)->assertCreated();

        $this->postJson('/api/v1/portal/'.$company->subdomain.'/offers/claim', $payload)
            ->assertStatus(422)
            ->assertJsonPath('data.offer.0', 'You have already claimed this offer.');

        $this->assertSame(1, $offer->fresh()->claims_count);
        $this->assertSame(1, OfferClaim::query()->where('offer_id', $offer->id)->count());
    }

    public function test_offer_max_claims_is_enforced(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $offer = Offer::query()->create([
            'company_id' => $company->id,
            'title' => 'First Only',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'max_claims' => 1,
            'is_active' => true,
            'claims_count' => 0,
        ]);

        $this->postJson('/api/v1/portal/'.$company->subdomain.'/offers/claim', [
            'offer_id' => $offer->id,
            'customer_phone' => '0711000001',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
        ])->assertCreated();

        $this->postJson('/api/v1/portal/'.$company->subdomain.'/offers/claim', [
            'offer_id' => $offer->id,
            'customer_phone' => '0711000002',
            'mac_address' => 'AA:BB:CC:DD:EE:02',
        ])->assertStatus(422);

        $this->assertSame(1, $offer->fresh()->claims_count);
    }

    public function test_inactive_and_out_of_window_offers_are_unavailable(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $inactive = Offer::query()->create([
            'company_id' => $company->id,
            'title' => 'Inactive',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'is_active' => false,
            'claims_count' => 0,
        ]);

        $future = Offer::query()->create([
            'company_id' => $company->id,
            'title' => 'Future',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'is_active' => true,
            'starts_at' => now()->addDay(),
            'claims_count' => 0,
        ]);

        $this->getJson('/api/v1/portal/'.$company->subdomain)
            ->assertOk()
            ->assertJsonPath('data.offer', null);

        foreach ([$inactive, $future] as $offer) {
            $this->postJson('/api/v1/portal/'.$company->subdomain.'/offers/claim', [
                'offer_id' => $offer->id,
                'customer_phone' => '0711987654',
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
            ])->assertStatus(422);
        }
    }

    public function test_claim_with_captive_session_authorizes_device(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);
        $headers = $this->authHeaders($owner);

        $routerId = $this->withHeaders($headers)->postJson('/api/v1/routers', [
            'gateway_type' => 'ruijie',
            'name' => 'Offer Router',
            'lan_ip' => '192.168.88.1',
            'gateway_id' => '58b4bb192d35',
        ])->assertCreated()->json('data.id');

        $router = NetworkDevice::query()->findOrFail($routerId);

        $offer = Offer::query()->create([
            'company_id' => $company->id,
            'title' => 'Free Hour',
            'duration' => 1,
            'duration_unit' => 'HOURS',
            'is_active' => true,
            'claims_count' => 0,
        ]);

        $token = str_repeat('a', 32);

        CaptiveSession::query()->create([
            'company_id' => $company->id,
            'network_device_id' => $router->id,
            'network_station_id' => $router->network_station_id,
            'gateway_id' => (string) $router->gateway_id,
            'client_mac' => 'AA:BB:CC:DD:EE:FF',
            'client_ip' => '192.168.1.50',
            'gw_address' => '192.168.88.1',
            'gw_port' => 2060,
            'token' => $token,
            'status' => CaptiveSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->postJson('/api/v1/portal/'.$company->subdomain.'/offers/claim', [
            'offer_id' => $offer->id,
            'customer_phone' => '0711987654',
            'captive_session' => $token,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])->assertCreated();

        $url = $response->json('data.captive.gateway_auth_url');
        $this->assertNotNull($url);
        $this->assertStringContainsString('/wifidog/auth', $url);

        $this->assertDatabaseHas('network_sessions', [
            'company_id' => $company->id,
            'status' => 'active',
        ]);
    }

    public function test_non_owner_cannot_manage_offers(): void
    {
        $user = $this->createUser();
        $this->createCompanyFor($user, 'operator');

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/offers', [
                'title' => 'Nope',
                'duration' => 1,
                'duration_unit' => 'HOURS',
            ])
            ->assertForbidden();
    }

    public function test_offer_service_standalone_uses_hidden_plan(): void
    {
        $owner = $this->createUser();
        $company = $this->createCompanyFor($owner);

        $offer = Offer::query()->create([
            'company_id' => $company->id,
            'title' => 'Hidden Plan Offer',
            'duration' => 4,
            'duration_unit' => 'HOURS',
            'is_active' => true,
            'claims_count' => 0,
        ]);

        $this->postJson('/api/v1/portal/'.$company->subdomain.'/offers/claim', [
            'offer_id' => $offer->id,
            'customer_phone' => '0711987654',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])->assertCreated();

        $grant = AccessGrant::query()->where('offer_id', $offer->id)->firstOrFail();
        $plan = $grant->internetPlan;

        $this->assertNotNull($plan);
        $this->assertSame('inactive', $plan->status);
        $this->assertSame('0.00', $plan->price);

        // Hidden plan must not appear as a purchasable package on the portal.
        $this->getJson('/api/v1/portal/'.$company->subdomain)
            ->assertOk()
            ->assertJsonPath('data.packages', []);
    }
}
