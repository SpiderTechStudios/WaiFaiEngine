<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\BrandSeeder',
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};