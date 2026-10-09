<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The percentage tolerance gains a cap, and each link keeps the signed difference it accepted.
     * The initial rule becomes: R$ 0,50, or 1% of the amount limited to R$ 200,00.
     */
    public function up(): void
    {
        Schema::table('reconciliation_settings', function (Blueprint $table) {
            $table->unsignedInteger('tolerance_cap_cents')->nullable()->after('tolerance_basis_points');
        });

        Schema::table('reconciliation_runs', function (Blueprint $table) {
            $table->unsignedInteger('tolerance_cap_cents')->nullable()->after('tolerance_basis_points');
        });

        Schema::table('reconciliation_links', function (Blueprint $table) {
            $table->bigInteger('difference_cents')->default(0)->after('difference_type');
        });

        DB::table('reconciliation_settings')
            ->whereNull('tolerance_basis_points')
            ->whereNull('updated_by')
            ->update(['tolerance_basis_points' => 100, 'tolerance_cap_cents' => 20000]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_links', function (Blueprint $table) {
            $table->dropColumn('difference_cents');
        });

        Schema::table('reconciliation_runs', function (Blueprint $table) {
            $table->dropColumn('tolerance_cap_cents');
        });

        Schema::table('reconciliation_settings', function (Blueprint $table) {
            $table->dropColumn('tolerance_cap_cents');
        });
    }
};
