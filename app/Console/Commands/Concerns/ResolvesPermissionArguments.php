<?php

namespace App\Console\Commands\Concerns;

use App\Enums\UserPermission;
use App\Models\User;

trait ResolvesPermissionArguments
{
    /**
     * Resolve the administrator, the user and the permission named on the command line.
     *
     * Returns null, after explaining why, when any of them cannot be resolved.
     *
     * @return array{0: User, 1: User, 2: UserPermission}|null
     */
    protected function resolvePermissionArguments(): ?array
    {
        $by = (string) $this->option('by');
        $email = (string) $this->argument('email');
        $permissionName = (string) $this->argument('permission');

        if ($by === '') {
            $this->error(__('conciliation.users.by_required'));

            return null;
        }

        $administrator = User::query()->where('email', $by)->first();

        if ($administrator === null) {
            $this->error(__('conciliation.users.not_found', ['email' => $by]));

            return null;
        }

        $permission = UserPermission::tryFrom($permissionName);

        if ($permission === null) {
            $this->error(__('conciliation.users.permission_unknown', [
                'permission' => $permissionName,
                'accepted' => implode(', ', array_column(UserPermission::cases(), 'value')),
            ]));

            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error(__('conciliation.users.not_found', ['email' => $email]));

            return null;
        }

        return [$administrator, $user, $permission];
    }
}
