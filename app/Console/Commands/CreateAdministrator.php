<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdministrator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conciliation:create-administrator {name : Full name of the user} {email : E-mail used to sign in}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an administrator, or promote the existing user with the given e-mail';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser !== null) {
            $existingUser->forceFill(['role' => UserRole::Administrador])->save();

            $this->info(__('conciliation.users.administrator_promoted', ['email' => $email]));

            return self::SUCCESS;
        }

        $password = (string) $this->secret(__('conciliation.users.password'));

        if (mb_strlen($password) < 8) {
            $this->error(__('conciliation.users.password_too_short'));

            return self::FAILURE;
        }

        User::query()->forceCreate([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Administrador,
            'email_verified_at' => now(),
        ]);

        $this->info(__('conciliation.users.administrator_created', ['email' => $email]));

        return self::SUCCESS;
    }
}
