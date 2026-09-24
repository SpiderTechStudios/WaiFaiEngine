<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('duration_unit')->nullable();
            $table->unsignedInteger('max_claims')->nullable();
            $table->unsignedInteger('claims_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('internet_plan_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'starts_at', 'ends_at']);
            $table->foreign(['internet_plan_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('internet_plans')
                ->restrictOnDelete();
        });

        // Empty pivot => offer is company-wide; rows => offer is router-scoped.
        Schema::create('offer_network_device', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('network_device_id');
            $table->timestamps();

            $table->unique(['offer_id', 'network_device_id']);
            $table->foreign('offer_id')->references('id')->on('offers')->cascadeOnDelete();
            $table->foreign('network_device_id')->references('id')->on('network_devices')->cascadeOnDelete();
        });

        Schema::create('offer_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('access_grant_id')->nullable();
            $table->unsignedBigInteger('captive_session_id')->nullable();
            $table->string('customer_phone');
            $table->string('device_mac', 32)->default('UNKNOWN');
            $table->timestamp('claimed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'customer_phone', 'device_mac'], 'offer_claims_unique');
            $table->index(['company_id', 'offer_id']);
            $table->index(['company_id', 'customer_phone']);
            $table->foreign(['offer_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('offers')
                ->restrictOnDelete();
            $table->foreign(['customer_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['access_grant_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('access_grants')
                ->restrictOnDelete();
        });

        Schema::table('access_grants', function (Blueprint $table) {
            $table->unsignedBigInteger('offer_id')->nullable()->after('voucher_id');

            $table->foreign(['offer_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('offers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('access_grants', function (Blueprint $table) {
            $table->dropForeign(['offer_id', 'company_id']);
            $table->dropColumn('offer_id');
        });

        Schema::dropIfExists('offer_claims');
        Schema::dropIfExists('offer_network_device');
        Schema::dropIfExists('offers');
    }
};
