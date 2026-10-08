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
        Schema::create('payment_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reconciliation_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('unit');
            $table->string('supplier_name', 500);
            $table->bigInteger('amount_cents');
            $table->bigInteger('obligation_amount_cents');
            $table->date('paid_on');
            $table->string('operation_code')->index();
            $table->string('operation_name', 500)->nullable();
            $table->string('species', 500)->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('obligation_number');
            $table->string('source_document')->nullable();
            $table->string('settlement_status')->nullable();
            $table->string('account_movement')->nullable();
            $table->string('identity_key', 800);
            $table->json('raw');
            $table->timestamp('created_at')->nullable();

            $table->unique(['import_file_id', 'row_number']);
            $table->index(['unit', 'identity_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_entries');
    }
};
