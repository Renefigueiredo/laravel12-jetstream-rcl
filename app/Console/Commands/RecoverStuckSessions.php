<?php

namespace App\Console\Commands;

use App\Contracts\ReconciliationEngine;
use App\Enums\SessionStatus;
use App\Models\ReconciliationSession;
use Illuminate\Console\Command;

class RecoverStuckSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conciliation:recover-stuck-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Return to the open status the sessions whose reconciliation stopped running';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recovered = ReconciliationSession::query()
            ->where('status', SessionStatus::Processing)
            ->where('processing_started_at', '<', now()->subMinutes((int) config('conciliation.stale.processing_minutes')))
            ->get()
            ->each(function (ReconciliationSession $session): void {
                if (app()->bound(ReconciliationEngine::class)) {
                    app(ReconciliationEngine::class)->discardResult($session);
                }

                $session->update([
                    'status' => SessionStatus::Open,
                    'progress' => null,
                    'last_failure' => __('conciliation.sessions.execute.stalled'),
                ]);
            })
            ->count();

        $this->info("Recovered sessions: {$recovered}.");

        return self::SUCCESS;
    }
}
