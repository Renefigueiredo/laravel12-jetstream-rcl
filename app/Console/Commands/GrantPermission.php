<?php

namespace App\Console\Commands;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\GrantUserPermission;
use App\Console\Commands\Concerns\ResolvesPermissionArguments;
use Illuminate\Console\Command;

class GrantPermission extends Command
{
    use ResolvesPermissionArguments;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conciliation:grant-permission
        {email : E-mail of the user who receives the permission}
        {permission : Permission to grant, for example manage_excluded_codes}
        {--by= : E-mail of the administrator responsible for the grant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant a permission to a user on behalf of an administrator';

    /**
     * Execute the console command.
     */
    public function handle(GrantUserPermission $grantUserPermission): int
    {
        $resolved = $this->resolvePermissionArguments();

        if ($resolved === null) {
            return self::FAILURE;
        }

        [$administrator, $user, $permission] = $resolved;

        try {
            $granted = $grantUserPermission->handle($administrator, $user, $permission);
        } catch (ActionRefusedException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(__($granted ? 'conciliation.users.permission_granted' : 'conciliation.users.permission_already_granted', [
            'permission' => $permission->label(),
            'email' => $user->email,
        ]));

        return self::SUCCESS;
    }
}
