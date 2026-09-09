<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeviceCatalogTest extends TestCase
{
    public function test_superadmin_can_manage_brands_categories_and_devices(): void
    {
        Storage::fake('public');

        $admin = $this->createUser(['is_superadmin' => true, 'status' => 'active']);
        $headers = $this->authHeaders($admin);

        $brand = $this->withHeaders($headers)
            ->post('/api/v1/superadmin/brands', [
                'name' => 'Catalog Test Brand',
                'description' => 'Routers and radios',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Catalog Test Brand')
            ->assertJsonPath('data.slug', 'catalog-test-brand');

        $this->assertNotNull($brand->json('data.logo_url'));
        Storage::disk('public')->assertExists($brand->json('data.logo'));

        $brandId = $brand->json('data.id');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/superadmin/brands/'.$brandId, [
                'description' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated');

        $category = $this->withHeaders($headers)
            ->postJson('/api/v1/superadmin/device-categories', [
                'name' => 'Access Point',
                'description' => 'Wireless AP',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Access Point');

        $categoryId = $category->json('data.id');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/superadmin/device-categories/'.$categoryId, [
                'description' => 'Indoor wireless AP',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Indoor wireless AP');

        $device = $this->withHeaders($headers)
            ->post('/api/v1/superadmin/devices', [
                'name' => 'hAP ac2',
                'sku' => 'RBD52G-5HACD2HND',
                'device_category_id' => $categoryId,
                'brand_id' => $brandId,
                'price' => 150000,
                'lan_ports' => 5,
                'image' => UploadedFile::fake()->image('device.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'RBD52G-5HACD2HND')
            ->assertJsonPath('data.brand.name', 'Catalog Test Brand')
            ->assertJsonPath('data.category.name', 'Access Point');

        $deviceId = $device->json('data.id');
        Storage::disk('public')->assertExists($device->json('data.image'));

        $this->withHeaders($headers)
            ->patchJson('/api/v1/superadmin/devices/'.$deviceId, [
                'price' => 160000,
                'is_featured' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.price', '160000.00')
            ->assertJsonPath('data.is_featured', true);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/superadmin/brands/'.$brandId)
            ->assertStatus(422);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/superadmin/devices/'.$deviceId)
            ->assertOk();

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/superadmin/brands/'.$brandId)
            ->assertOk();

        $this->assertDatabaseMissing('brands', ['id' => $brandId]);
        $this->assertDatabaseMissing('devices', ['id' => $deviceId]);
        $this->assertDatabaseHas('device_categories', ['id' => $categoryId]);
    }

    public function test_non_superadmin_cannot_manage_catalog(): void
    {
        $owner = $this->createUser();
        $this->createCompanyFor($owner);

        $this->withHeaders($this->authHeaders($owner))
            ->getJson('/api/v1/superadmin/brands')
            ->assertForbidden();

        $this->withHeaders($this->authHeaders($owner))
            ->getJson('/api/v1/superadmin/devices')
            ->assertForbidden();
    }

    public function test_missing_catalog_records_use_plain_not_found_messages(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'status' => 'active']);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/superadmin/brands/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Brand not found.');

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/superadmin/devices/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Device not found.');
    }
}
