<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subdomain')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('status')->default('pending')->index();
            $table->string('primary_color')->default('#0F4C81');
            $table->string('logo_url')->nullable();
            $table->unsignedTinyInteger('voucher_code_digits')->default(6);
            $table->enum('payment_method', ['mobile_money', 'voucher', 'both'])->default('mobile_money');
            $table->text('captive_portal_welcome_message')->nullable();
            $table->string('ruijie_account_id')->nullable();
            $table->text('ruijie_password')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_company_id')->nullable()->after('last_login_at')->constrained('companies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_company_id');
        });

        Schema::dropIfExists('companies');
    }
};
