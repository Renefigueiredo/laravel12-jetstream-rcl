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
        Schema::create('authorization_forecasts', function (Blueprint $table) {
            $table->foreignId('authorization_entry_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('expected_count');
            $table->unsignedSmallInteger('paid_count');
            $table->unsignedSmallInteger('overdue_count')->index();
            $table->date('next_expected_month')->nullable();
            $table->boolean('from_plan');
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorization_forecasts');
    }
};
