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
        Schema::create('reconciliation_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authorization_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_entry_id')->constrained()->cascadeOnDelete();
            $table->string('classification');
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('supplier_score');
            $table->unsignedSmallInteger('amount_score');
            $table->bigInteger('difference_cents');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_tie')->default(false);
            $table->boolean('paid_before_authorization')->default(false);
            $table->boolean('card_mismatch')->default(false);
            $table->string('status')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['reconciliation_run_id', 'authorization_entry_id', 'payment_entry_id'], 'reconciliation_suggestions_pair_unique');
            $table->index(['authorization_entry_id', 'status', 'position'], 'reconciliation_suggestions_authorization_index');
            $table->index(['payment_entry_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_suggestions');
    }
};
