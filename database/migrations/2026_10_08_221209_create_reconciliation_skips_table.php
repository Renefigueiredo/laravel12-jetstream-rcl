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
        Schema::create('reconciliation_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_entry_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('authorization_entry_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->string('operation_code', 20)->nullable();
            $table->foreignId('original_session_id')->nullable()->constrained('reconciliation_sessions')->nullOnDelete();

            $table->unique(['reconciliation_run_id', 'payment_entry_id']);
            $table->unique(['reconciliation_run_id', 'authorization_entry_id']);
            $table->index(['reconciliation_run_id', 'operation_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_skips');
    }
};
