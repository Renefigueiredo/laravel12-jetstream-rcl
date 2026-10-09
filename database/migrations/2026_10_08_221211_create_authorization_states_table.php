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
        Schema::create('authorization_states', function (Blueprint $table) {
            $table->foreignId('authorization_entry_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('links_count');
            $table->unsignedBigInteger('paid_cents');
            $table->unsignedBigInteger('discount_cents');
            $table->unsignedBigInteger('writeoff_cents');
            $table->unsignedBigInteger('balance_cents');
            $table->string('status')->index();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorization_states');
    }
};
