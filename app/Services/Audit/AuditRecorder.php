<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditRecorder
{
    /**
     * Record an audit entry for a change being made in the current transaction.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(User $actor, AuditAction $action, Model $entity, string $label, ?array $before, ?array $after): AuditLog
    {
        if ($entity->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Audit records must be written in the same transaction as the change.');
        }

        return AuditLog::query()->create([
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => $entity->getMorphClass(),
            'auditable_id' => $entity->getKey(),
            'label' => $label,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
