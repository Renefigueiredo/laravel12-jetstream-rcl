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
        Schema::create('import_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_session_id')->constrained()->cascadeOnDelete();
            $table->string('slot');
            $table->string('status')->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('original_name');
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedSmallInteger('progress')->default(0);
            $table->unsignedSmallInteger('sheet_count')->nullable();
            $table->json('missing_columns')->nullable();
            $table->unsignedInteger('rows_total')->nullable();
            $table->unsignedInteger('rows_valid')->nullable();
            $table->unsignedInteger('rows_skipped_value')->nullable();
            $table->unsignedInteger('rows_out_of_period')->nullable();
            $table->date('min_date')->nullable();
            $table->date('max_date')->nullable();
            $table->unsignedInteger('error_count')->default(0);
            $table->json('first_errors')->nullable();
            $table->string('error_report_path')->nullable();
            $table->string('message', 1000)->nullable();
            $table->foreignId('divergence_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('divergence_confirmed_at')->nullable();
            $table->foreignId('import_file_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['reconciliation_session_id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_attempts');
    }
};
