<?php

namespace App\Enums;

enum ImportAttemptStatus: string
{
    case Queued = 'queued';
    case Validating = 'validating';
    case Rejected = 'rejected';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Persisting = 'persisting';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /**
     * Whether the attempt is still being worked on by a queued job.
     */
    public function isInProgress(): bool
    {
        return in_array($this, self::inProgress(), true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Rejected, self::Accepted, self::Cancelled, self::Failed], true);
    }

    /**
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::Queued, self::Validating, self::Persisting];
    }
}
