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
        Schema::create('authorization_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reconciliation_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('request', 1000);
            $table->string('supplier_name', 500);
            $table->bigInteger('amount_cents');
            $table->date('authorized_on');
            $table->string('payment_method')->nullable();
            $table->string('card')->nullable();
            $table->string('payment_condition')->nullable();
            $table->char('identity_key', 64)->index();
            $table->json('raw');
            $table->timestamp('created_at')->nullable();

            $table->unique(['import_file_id', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorization_entries');
    }
};
