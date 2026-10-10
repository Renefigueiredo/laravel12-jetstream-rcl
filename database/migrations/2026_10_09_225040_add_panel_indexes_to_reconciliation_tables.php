<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A foreign key does not bring an index with it. These are the ones the panel across
     * sessions reads by: the links and the skipped entries of each authorization and payment.
     */
    public function up(): void
    {
        Schema::table('authorization_states', function (Blueprint $table) {
            $table->index('balance_cents');
        });

        Schema::table('reconciliation_links', function (Blueprint $table) {
            $table->index(['authorization_entry_id', 'treatment']);
            $table->index('reconciliation_run_id');
        });

        Schema::table('reconciliation_skips', function (Blueprint $table) {
            $table->index('authorization_entry_id');
            $table->index('payment_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_skips', function (Blueprint $table) {
            $table->dropIndex(['payment_entry_id']);
            $table->dropIndex(['authorization_entry_id']);
        });

        Schema::table('reconciliation_links', function (Blueprint $table) {
            $table->dropIndex(['reconciliation_run_id']);
            $table->dropIndex(['authorization_entry_id', 'treatment']);
        });

        Schema::table('authorization_states', function (Blueprint $table) {
            $table->dropIndex(['balance_cents']);
        });
    }
};
