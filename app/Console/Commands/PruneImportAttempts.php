<?php

namespace App\Console\Commands;

use App\Actions\Conciliation\CancelImportAttempt;
use App\Enums\ImportAttemptStatus;
use App\Models\ImportAttempt;
use Illuminate\Console\Command;

class PruneImportAttempts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conciliation:prune-import-attempts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel expired confirmations, fail stalled uploads and remove old import attempts';

    /**
     * Execute the console command.
     */
    public function handle(CancelImportAttempt $cancelImportAttempt): int
    {
        $expired = ImportAttempt::query()
            ->where('status', ImportAttemptStatus::AwaitingConfirmation)
            ->where('updated_at', '<', now()->subMinutes((int) config('conciliation.attempts.confirmation_ttl_minutes')))
            ->get()
            ->filter(fn (ImportAttempt $attempt): bool => $cancelImportAttempt->handle($attempt))
            ->count();

        $stalled = ImportAttempt::query()
            ->whereIn('status', ImportAttemptStatus::inProgress())
            ->where('updated_at', '<', now()->subMinutes((int) config('conciliation.stale.attempt_minutes')))
            ->get()
            ->each(function (ImportAttempt $attempt): void {
                $attempt->update(['status' => ImportAttemptStatus::Failed, 'message' => __('conciliation.import.stalled')]);
                $attempt->deleteReceivedFile();
            })
            ->count();

        $removed = ImportAttempt::query()
            ->whereIn('status', [
                ImportAttemptStatus::Rejected,
                ImportAttemptStatus::Accepted,
                ImportAttemptStatus::Cancelled,
                ImportAttemptStatus::Failed,
            ])
            ->where('updated_at', '<', now()->subHours((int) config('conciliation.attempts.retention_hours')))
            ->get()
            ->each(function (ImportAttempt $attempt): void {
                $attempt->deleteErrorReport();

                if ($attempt->status !== ImportAttemptStatus::Accepted) {
                    $attempt->deleteReceivedFile();
                }

                $attempt->delete();
            })
            ->count();

        $this->info("Expired confirmations: {$expired}. Stalled uploads: {$stalled}. Removed attempts: {$removed}.");

        return self::SUCCESS;
    }
}
