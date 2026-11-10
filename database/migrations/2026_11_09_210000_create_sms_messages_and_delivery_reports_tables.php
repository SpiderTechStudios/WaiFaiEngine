<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('nextsms');
            $table->string('provider_message_id')->nullable()->index();
            $table->string('reference')->nullable()->index();
            $table->string('sender')->nullable();
            $table->string('recipient');
            $table->text('text');
            $table->string('status')->default('queued')->index();
            $table->string('status_group')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['recipient', 'status']);
        });

        Schema::create('sms_delivery_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_message_id')->nullable()->constrained('sms_messages')->nullOnDelete();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('reference')->nullable()->index();
            $table->string('recipient')->nullable();
            $table->string('status')->nullable();
            $table->string('status_group')->nullable();
            $table->string('error_name')->nullable();
            $table->string('error_description')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('raw_payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_delivery_reports');
        Schema::dropIfExists('sms_messages');
    }
};
