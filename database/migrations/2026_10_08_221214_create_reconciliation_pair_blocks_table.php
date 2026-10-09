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
        Schema::create('reconciliation_pair_blocks', function (Blueprint $table) {
            $table->id();
            $table->char('authorization_identity_key', 64);
            $table->string('payment_unit');
            $table->string('payment_identity_key', 800);
            $table->string('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->unique(['authorization_identity_key', 'payment_unit', 'payment_identity_key'], 'reconciliation_pair_blocks_pair_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_pair_blocks');
    }
};
