<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canonical mobile-money networks supported by the platform.
     *
     * @var list<array{key: string, name: string, prefixes: list<string>, sort_order: int}>
     */
    private array $networks = [
        ['key' => 'mixxbyyas', 'name' => 'Mixx by Yas', 'prefixes' => ['065', '067', '071', '077'], 'sort_order' => 1],
        ['key' => 'mpesa', 'name' => 'M-Pesa', 'prefixes' => ['074', '075', '076', '079'], 'sort_order' => 2],
        ['key' => 'airtelmoney', 'name' => 'Airtel Money', 'prefixes' => ['068', '069', '078'], 'sort_order' => 3],
        ['key' => 'halopesa', 'name' => 'HaloPesa', 'prefixes' => ['061', '062'], 'sort_order' => 4],
        ['key' => 'tpesa', 'name' => 'TTCL Pesa (T-Pesa)', 'prefixes' => ['073'], 'sort_order' => 5],
    ];

    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('type')->default('mobile_money')->index();
            $table->string('logo_url')->nullable();
            $table->json('prefixes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach ($this->networks as $network) {
            DB::table('payment_methods')->insertOrIgnore([
                'key' => $network['key'],
                'name' => $network['name'],
                'type' => 'mobile_money',
                'logo_url' => null,
                'prefixes' => json_encode($network['prefixes']),
                'is_active' => true,
                'sort_order' => $network['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
