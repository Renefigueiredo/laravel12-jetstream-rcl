<?php

namespace App\Actions\Conciliation;

use App\Enums\AuditAction;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class RevokeUserPermission
{
    public function __construct(protected AuditRecorder $audit) {}

    /**
     * Revoke a permission from a user on behalf of an administrator.
     *
     * Returns false when the user did not have the permission.
     *
     * @throws ActionRefusedException
     */
    public function handle(User $administrator, User $user, UserPermission $permission): bool
    {
        if ($administrator->role !== UserRole::Administrador) {
            throw new ActionRefusedException(__('conciliation.users.by_not_administrator', ['email' => $administrator->email]));
        }

        return DB::transaction(function () use ($administrator, $user, $permission): bool {
            $grant = UserPermissionGrant::query()
                ->where('user_id', $user->id)
                ->where('permission', $permission)
                ->lockForUpdate()
                ->first();

            if ($grant === null) {
                return false;
            }

            $this->audit->record(
                $administrator,
                AuditAction::PermissionRevoked,
                $grant,
                $user->email,
                ['user_id' => $user->id, 'email' => $user->email, 'permission' => $permission->value],
                null,
            );

            $grant->delete();

            return true;
        });
    }
}
