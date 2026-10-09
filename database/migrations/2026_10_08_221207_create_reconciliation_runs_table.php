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
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_session_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('tolerance_cents');
            $table->unsignedInteger('tolerance_basis_points')->nullable();
            $table->unsignedInteger('surcharge_cap_basis_points');
            $table->unsignedSmallInteger('automatic_threshold');
            $table->unsignedSmallInteger('suggestion_threshold');
            $table->unsignedSmallInteger('supplier_threshold');
            $table->unsignedSmallInteger('lookback_months');
            $table->json('excluded_codes');
            $table->json('totals')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
