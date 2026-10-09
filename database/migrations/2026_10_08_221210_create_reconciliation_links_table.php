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
        Schema::create('reconciliation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorization_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reconciliation_run_id')->constrained()->restrictOnDelete();
            $table->string('origin');
            $table->boolean('is_installment')->default(false);
            $table->string('engine_classification')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('supplier_score')->nullable();
            $table->unsignedSmallInteger('amount_score')->nullable();
            $table->string('difference_type');
            $table->unsignedBigInteger('excess_cents')->default(0);
            $table->string('treatment')->nullable();
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('tolerance_writeoff_cents')->default(0);
            $table->unsignedInteger('surcharge_cap_basis_points')->nullable();
            $table->string('justification_category')->nullable();
            $table->string('justification', 500)->nullable();
            $table->boolean('paid_before_authorization')->default(false)->index();
            $table->boolean('card_mismatch')->default(false);
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_links');
    }
};
