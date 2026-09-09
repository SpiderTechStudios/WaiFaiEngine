<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_category_id')
                ->constrained('device_categories')
                ->restrictOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            // Basic information
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('model')->nullable();
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();

            // Pricing & inventory
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);

            // Network information
            $table->string('network_type')->nullable();
            $table->string('device_type')->nullable();
            $table->string('operating_system')->nullable();

            // Ports
            $table->unsignedInteger('lan_ports')->nullable();
            $table->unsignedInteger('wan_ports')->nullable();
            $table->unsignedInteger('sfp_ports')->nullable();
            $table->unsignedInteger('sfp_plus_ports')->nullable();
            $table->unsignedInteger('usb_ports')->nullable();
            $table->unsignedInteger('console_ports')->nullable();

            // Ethernet
            $table->string('ethernet_speed')->nullable();
            $table->boolean('gigabit')->nullable();

            // Wi-Fi
            $table->string('wifi_standard')->nullable();
            $table->string('wifi_speed')->nullable();
            $table->string('wifi_frequency')->nullable();
            $table->unsignedInteger('max_wifi_clients')->nullable();
            $table->unsignedInteger('antenna_count')->nullable();
            $table->string('antenna_type')->nullable();
            $table->string('antenna_gain')->nullable();

            // Cellular / Mobile network
            $table->boolean('sim_support')->nullable();
            $table->string('sim_type')->nullable();
            $table->string('cellular_network')->nullable();
            $table->string('cellular_speed')->nullable();
            $table->string('supported_bands')->nullable();

            // PoE
            $table->boolean('poe_support')->nullable();
            $table->string('poe_standard')->nullable();
            $table->string('poe_power')->nullable();

            // Routing
            $table->boolean('routing_support')->nullable();
            $table->unsignedInteger('max_routes')->nullable();
            $table->string('routing_protocols')->nullable();

            // Switching
            $table->boolean('managed')->nullable();
            $table->boolean('vlan_support')->nullable();
            $table->boolean('qos_support')->nullable();
            $table->string('switching_capacity')->nullable();
            $table->string('forwarding_rate')->nullable();

            // Security
            $table->boolean('firewall_support')->nullable();
            $table->boolean('vpn_support')->nullable();
            $table->string('vpn_protocols')->nullable();
            $table->string('security_features')->nullable();

            // Physical
            $table->string('dimensions')->nullable();
            $table->string('weight')->nullable();
            $table->string('mounting')->nullable();
            $table->string('ip_rating')->nullable();

            // Power
            $table->string('power_input')->nullable();
            $table->string('power_consumption')->nullable();

            // General
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_configurable')->default(false);

            $table->timestamps();

            $table->index(['device_category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
