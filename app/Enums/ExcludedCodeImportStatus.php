<?php

namespace App\Enums;

enum ExcludedCodeImportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    /**
     * Whether the import is still being worked on by a queued job.
     */
    public function isInProgress(): bool
    {
        return in_array($this, self::inProgress(), true);
    }

    /**
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::Queued, self::Processing];
    }
}
