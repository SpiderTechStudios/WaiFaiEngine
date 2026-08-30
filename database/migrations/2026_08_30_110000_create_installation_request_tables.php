<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('service_type');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('payment_status')->default('pending')->index();
            $table->string('fulfillment_status')->default('requested')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'fulfillment_status']);
            $table->index(['company_id', 'payment_status']);
        });

        Schema::create('installation_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->string('label')->nullable();
            $table->foreignId('network_device_id')->nullable()->constrained('network_devices')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['installation_request_id', 'position'], 'inst_req_item_position_unique');
        });

        Schema::create('installation_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('field')->default('fulfillment_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['installation_request_id', 'created_at'], 'inst_req_history_created_idx');
        });

        Schema::create('installation_request_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_request_id')->constrained()->cascadeOnDelete();
            $table->string('visibility'); // customer | internal
            $table->text('body');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['installation_request_id', 'visibility'], 'inst_req_updates_vis_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_request_updates');
        Schema::dropIfExists('installation_request_status_histories');
        Schema::dropIfExists('installation_request_items');
        Schema::dropIfExists('installation_requests');
    }
};
