<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payments', function (Blueprint $table) {
            $table->foreignId('payment_provider_id')
                ->nullable()
                ->after('id')
                ->constrained('payment_providers')
                ->nullOnDelete();
            $table->string('provider_slug')->nullable()->after('payment_provider_id')->index();
            $table->string('purpose')->nullable()->after('type')->index();
            $table->string('direction')->default('collection')->after('purpose')->index();
            $table->string('provider_event_id')->nullable()->after('external_reference')->index();
            $table->timestamp('processed_at')->nullable()->after('cancelled_at');
        });

        foreach (DB::table('platform_payments')->orderBy('id')->get() as $row) {
            $purpose = match ($row->type) {
                'platform_subscription', 'signup' => 'platform_subscription',
                'subscription_renewal' => 'subscription_renewal',
                'installation' => 'installation_request',
                default => $row->type,
            };

            $meta = json_decode((string) ($row->metadata ?? '{}'), true) ?: [];
            if (! empty($meta['payment_purpose'])) {
                $purpose = (string) $meta['payment_purpose'];
            }

            DB::table('platform_payments')->where('id', $row->id)->update([
                'purpose' => $purpose,
                'direction' => 'collection',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('platform_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_provider_id');
            $table->dropColumn([
                'provider_slug',
                'purpose',
                'direction',
                'provider_event_id',
                'processed_at',
            ]);
        });
    }
};
