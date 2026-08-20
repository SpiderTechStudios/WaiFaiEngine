<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internet_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('badge')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('duration_unit');
            $table->decimal('price', 12, 2);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'company_id']);
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internet_plans');
    }
};
