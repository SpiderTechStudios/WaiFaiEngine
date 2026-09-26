<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('router_id')->nullable();
            $table->foreignId('expense_type_id')->constrained('expense_types')->restrictOnDelete();

            // Denormalised from expense_type so list/summary filtering stays cheap.
            $table->string('category')->index();

            // manual = entered by a user, system = generated from another transaction.
            $table->string('source')->default('manual')->index();
            $table->nullableMorphs('source');

            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('TZS');
            $table->date('paid_at');
            $table->string('paid_to');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('recorded');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id', 'company_id']);
            $table->index(['company_id', 'paid_at']);
            $table->index(['company_id', 'expense_type_id']);
            $table->index(['company_id', 'category']);
            $table->index(['company_id', 'router_id']);
            $table->index(['company_id', 'source']);
            $table->index(['company_id', 'currency']);
            $table->index(['router_id', 'company_id']);

            // Tenant-safe router association: a router must belong to the same company.
            $table->foreign(['router_id', 'company_id'])
                ->references(['id', 'company_id'])
                ->on('network_devices')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
