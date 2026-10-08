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
        Schema::create('import_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_session_id')->constrained()->cascadeOnDelete();
            $table->string('slot');
            $table->string('status');
            $table->string('original_name');
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedSmallInteger('sheet_count')->default(1);
            $table->json('missing_columns')->nullable();
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped_value')->default(0);
            $table->unsignedInteger('rows_skipped_existing')->default(0);
            $table->unsignedInteger('rows_out_of_period')->default(0);
            $table->date('min_date')->nullable();
            $table->date('max_date')->nullable();
            $table->boolean('period_divergence')->default(false);
            $table->foreignId('divergence_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('divergence_confirmed_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();

            $table->index(['reconciliation_session_id', 'sha256']);
        });

        DB::statement("CREATE UNIQUE INDEX import_files_active_slot_unique ON import_files (reconciliation_session_id, slot) WHERE status = 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_files');
    }
};
