<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\SessionStatus;
use App\Models\ReconciliationSession;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSession
{
    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Create a reconciliation session for a reference month (MM/AAAA).
     *
     * @throws ValidationException
     * @throws ComplementarySessionConfirmationRequired
     */
    public function handle(User $user, string $period, bool $complementaryConfirmed = false): ReconciliationSession
    {
        $periodDate = $this->parsePeriod($period);

        $periodHasSession = ReconciliationSession::query()->whereDate('period', $periodDate)->exists();

        if ($periodHasSession && ! $complementaryConfirmed) {
            throw new ComplementarySessionConfirmationRequired($periodDate->format('m/Y'));
        }

        return DB::transaction(function () use ($user, $periodDate): ReconciliationSession {
            $session = ReconciliationSession::query()->create([
                'period' => $periodDate->toDateString(),
                'status' => SessionStatus::Open,
                'created_by' => $user->id,
            ]);

            $this->audit->record($user, AuditAction::SessionCreated, $session, $session->label(), null, [
                'period' => $session->periodLabel(),
                'status' => SessionStatus::Open->value,
            ]);

            return $session;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function parsePeriod(string $period): CarbonImmutable
    {
        if (preg_match('/^(0?[1-9]|1[0-2])\/(\d{4})$/', trim($period), $matches) !== 1) {
            throw ValidationException::withMessages(['period' => __('conciliation.sessions.errors.period_format')]);
        }

        $periodDate = CarbonImmutable::create((int) $matches[2], (int) $matches[1], 1, 0, 0, 0, 'UTC');

        $currentMonth = CarbonImmutable::now(config('conciliation.display_timezone'))->format('Y-m');

        if ($periodDate->format('Y-m') > $currentMonth) {
            throw ValidationException::withMessages(['period' => __('conciliation.sessions.errors.period_future')]);
        }

        return $periodDate;
    }
}
