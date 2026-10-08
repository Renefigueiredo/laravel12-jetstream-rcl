<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\ExcludedCodeSource;
use App\Models\ExcludedOperationCode;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\ExcludedCodes\OperationCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AddExcludedCode
{
    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Add an operation code to the list of codes left out of the reconciliation.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $user, ?string $code, ?string $description): ExcludedOperationCode
    {
        Gate::forUser($user)->authorize('manage-excluded-codes');

        $code = OperationCode::normalize($code);
        $description = OperationCode::normalize($description);

        if ($code === null) {
            throw new ActionRefusedException(__('conciliation.excluded_codes.errors.code_required'));
        }

        if (! OperationCode::isValid($code)) {
            throw new ActionRefusedException(__('conciliation.excluded_codes.errors.code_invalid'));
        }

        if ($description !== null && mb_strlen($description) > OperationCode::DESCRIPTION_MAX_LENGTH) {
            throw new ActionRefusedException(__('conciliation.excluded_codes.errors.description_max'));
        }

        if (ExcludedOperationCode::query()->where('code', $code)->exists()) {
            throw new ActionRefusedException(__('conciliation.excluded_codes.errors.duplicate', ['code' => $code]));
        }

        try {
            return DB::transaction(function () use ($user, $code, $description): ExcludedOperationCode {
                $excludedCode = ExcludedOperationCode::query()->create([
                    'code' => $code,
                    'description' => $description,
                    'source' => ExcludedCodeSource::Manual,
                    'created_by' => $user->id,
                ]);

                $this->audit->record(
                    $user,
                    AuditAction::ExcludedCodeAdded,
                    $excludedCode,
                    $code,
                    null,
                    ['code' => $code, 'description' => $description],
                );

                return $excludedCode;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ActionRefusedException(__('conciliation.excluded_codes.errors.duplicate', ['code' => $code]));
        }
    }
}
