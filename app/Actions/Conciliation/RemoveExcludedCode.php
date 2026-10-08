<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Models\ExcludedOperationCode;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveExcludedCode
{
    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Remove an operation code from the list; it stops excluding payments on the next executions.
     *
     * Returns the code that was removed.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, int $excludedCodeId): string
    {
        Gate::forUser($user)->authorize('manage-excluded-codes');

        return DB::transaction(function () use ($user, $excludedCodeId): string {
            $excludedCode = ExcludedOperationCode::query()->lockForUpdate()->find($excludedCodeId);

            if ($excludedCode === null) {
                throw new ActionRefusedException(__('conciliation.excluded_codes.remove.already_removed'));
            }

            $this->audit->record(
                $user,
                AuditAction::ExcludedCodeRemoved,
                $excludedCode,
                $excludedCode->code,
                [
                    'code' => $excludedCode->code,
                    'description' => $excludedCode->description,
                    'source' => $excludedCode->source->value,
                    'created_at' => $excludedCode->created_at?->toIso8601String(),
                ],
                null,
            );

            $excludedCode->delete();

            return $excludedCode->code;
        });
    }
}
