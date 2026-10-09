<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reconciliation_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tolerance_cents')->default(50);
            $table->unsignedInteger('tolerance_basis_points')->nullable();
            $table->unsignedInteger('surcharge_cap_basis_points')->default(1000);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::table('reconciliation_settings')->insert([
            'tolerance_cents' => 50,
            'tolerance_basis_points' => null,
            'surcharge_cap_basis_points' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_settings');
    }
};
