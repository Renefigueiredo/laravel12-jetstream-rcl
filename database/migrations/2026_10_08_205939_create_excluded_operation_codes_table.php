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
        Schema::create('excluded_operation_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description', 255)->nullable();
            $table->string('source');
            $table->foreignId('excluded_code_import_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excluded_operation_codes');
    }
};
