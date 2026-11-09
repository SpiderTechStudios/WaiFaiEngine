<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreignId('payout_account_id')->nullable()->constrained('payout_accounts')->nullOnDelete();
            $table->foreignId('payment_provider_id')->nullable()->constrained('payment_providers')->nullOnDelete();
            $table->string('payment_provider_slug')->nullable()->index();
            $table->string('network')->nullable();

            $table->decimal('platform_fee', 14, 2)->default(0);
            $table->decimal('provider_fee', 14, 2)->default(0);
            $table->decimal('fee_total', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->json('fee_breakdown')->nullable();

            $table->string('otp_hash')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('otp_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_account_id');
            $table->dropConstrainedForeignId('payment_provider_id');
            $table->dropColumn([
                'payment_provider_slug',
                'network',
                'platform_fee',
                'provider_fee',
                'fee_total',
                'net_amount',
                'fee_breakdown',
                'otp_hash',
                'otp_expires_at',
                'otp_attempts',
                'otp_sent_at',
                'confirmed_at',
            ]);
        });
    }
};
