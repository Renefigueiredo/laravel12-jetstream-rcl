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
        Schema::table('authorization_entries', function (Blueprint $table) {
            $table->foreignId('import_file_id')->nullable()->change();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('source_payment_entry_id')->nullable()->constrained('payment_entries')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authorization_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_payment_entry_id');
            $table->dropConstrainedForeignId('created_by');
            $table->foreignId('import_file_id')->nullable(false)->change();
        });
    }
};
