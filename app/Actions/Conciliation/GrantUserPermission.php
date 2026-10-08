<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class GrantUserPermission
{
    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Grant a permission to a user on behalf of an administrator.
     *
     * Returns false when the user already had the permission.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $administrator, User $user, UserPermission $permission): bool
    {
        if ($administrator->role !== UserRole::Administrador) {
            throw new ActionRefusedException(__('conciliation.users.by_not_administrator', ['email' => $administrator->email]));
        }

        return DB::transaction(function () use ($administrator, $user, $permission): bool {
            $grant = UserPermissionGrant::query()->firstOrCreate(
                ['user_id' => $user->id, 'permission' => $permission],
                ['granted_by' => $administrator->id],
            );

            if (! $grant->wasRecentlyCreated) {
                return false;
            }

            $this->audit->record(
                $administrator,
                AuditAction::PermissionGranted,
                $grant,
                $user->email,
                null,
                ['user_id' => $user->id, 'email' => $user->email, 'permission' => $permission->value],
            );

            return true;
        });
    }
}
