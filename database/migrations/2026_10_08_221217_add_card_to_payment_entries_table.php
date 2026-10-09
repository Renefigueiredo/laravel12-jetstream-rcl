<?php

use App\Services\Reconciliation\Matching\CardNumberExtractor;
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
        Schema::table('payment_entries', function (Blueprint $table) {
            Schema::table('payment_entries', function (Blueprint $table) {
                $table->string('card', 4)->nullable()->index();
            });

            $marker = (string) config('conciliation.engine.card_species_marker');

            DB::table('payment_entries')
                ->whereNotNull('species')
                ->select(['id', 'species'])
                ->orderBy('id')
                ->chunkById(1000, function ($payments) use ($marker): void {
                    foreach ($payments as $payment) {
                        $card = CardNumberExtractor::extract($payment->species, $marker);

                        if ($card !== null) {
                            DB::table('payment_entries')->where('id', $payment->id)->update(['card' => $card]);
                        }
                    }
                });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_entries', function (Blueprint $table) {
            Schema::table('payment_entries', function (Blueprint $table) {
                $table->dropIndex(['card']);
                $table->dropColumn('card');
            });
        });
    }
};
