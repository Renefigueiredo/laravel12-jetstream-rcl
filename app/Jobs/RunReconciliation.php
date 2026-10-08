<?php

namespace App\Jobs;

use App\Contracts\ReconciliationEngine;
use App\Enums\SessionStatus;
use App\Models\ReconciliationSession;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunReconciliation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function __construct(public int $sessionId) {}

    public function uniqueId(): string
    {
        return (string) $this->sessionId;
    }

    /**
     * Run the reconciliation engine for a session that is locked for processing.
     */
    public function handle(ReconciliationEngine $engine): void
    {
        $session = ReconciliationSession::query()->find($this->sessionId);

        if ($session === null || $session->status !== SessionStatus::Processing) {
            return;
        }

        $engine->discardResult($session);

        $engine->run($session, function (int $percent) use ($session): void {
            ReconciliationSession::query()->whereKey($session->id)->update(['progress' => max(0, min(100, $percent))]);
        });

        $finishedAt = now();

        ReconciliationSession::query()
            ->whereKey($session->id)
            ->where('status', SessionStatus::Processing)
            ->update([
                'status' => SessionStatus::Processed,
                'first_processed_at' => $session->first_processed_at ?? $finishedAt,
                'processed_at' => $finishedAt,
                'progress' => 100,
                'result_stale' => false,
                'last_failure' => null,
            ]);
    }

    /**
     * A failed run leaves no partial result and returns the session to the open status.
     */
    public function failed(?Throwable $exception): void
    {
        $session = ReconciliationSession::query()->find($this->sessionId);

        if ($session === null || $session->status !== SessionStatus::Processing) {
            return;
        }

        app(ReconciliationEngine::class)->discardResult($session);

        $session->update([
            'status' => SessionStatus::Open,
            'progress' => null,
            'last_failure' => $exception?->getMessage() ?: __('conciliation.sessions.execute.stalled'),
        ]);
    }
}
