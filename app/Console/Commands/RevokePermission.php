<?php

namespace App\Console\Commands;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\RevokeUserPermission;
use App\Console\Commands\Concerns\ResolvesPermissionArguments;
use Illuminate\Console\Command;

class RevokePermission extends Command
{
    use ResolvesPermissionArguments;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conciliation:revoke-permission
        {email : E-mail of the user who loses the permission}
        {permission : Permission to revoke, for example manage_excluded_codes}
        {--by= : E-mail of the administrator responsible for the revocation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke a permission from a user on behalf of an administrator';

    /**
     * Execute the console command.
     */
    public function handle(RevokeUserPermission $revokeUserPermission): int
    {
        $resolved = $this->resolvePermissionArguments();

        if ($resolved === null) {
            return self::FAILURE;
        }

        [$administrator, $user, $permission] = $resolved;

        try {
            $revoked = $revokeUserPermission->handle($administrator, $user, $permission);
        } catch (ActionRefusedException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(__($revoked ? 'conciliation.users.permission_revoked' : 'conciliation.users.permission_not_granted', [
            'permission' => $permission->label(),
            'email' => $user->email,
        ]));

        return self::SUCCESS;
    }
}
