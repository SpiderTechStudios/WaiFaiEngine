<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status')->default('pending_payment')->index();
            $table->decimal('subscription_fee', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('business_name');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone', 50);
            $table->string('payment_phone', 50);
            $table->text('address');
            $table->string('portal_subdomain')->nullable()->index();
            $table->string('password_hash');
            $table->timestamp('expires_at')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_intents');
    }
};
