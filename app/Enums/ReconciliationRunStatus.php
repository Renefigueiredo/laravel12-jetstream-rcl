<?php

namespace App\Enums;

enum ReconciliationRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Discarded = 'discarded';

    public function label(): string
    {
        return __('conciliation.reconciliation.run_status.'.$this->value);
    }
}
