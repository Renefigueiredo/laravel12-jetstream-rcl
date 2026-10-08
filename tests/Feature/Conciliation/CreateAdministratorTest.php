<?php

namespace Tests\Feature\Conciliation;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_administrator(): void
    {
        $this->artisan('conciliation:create-administrator', ['name' => 'Ana Lima', 'email' => 'ana@example.com'])
            ->expectsQuestion(__('conciliation.users.password'), 'segredo-forte')
            ->expectsOutputToContain(__('conciliation.users.administrator_created', ['email' => 'ana@example.com']))
            ->assertSuccessful();

        $user = User::query()->where('email', 'ana@example.com')->sole();

        $this->assertSame('Ana Lima', $user->name);
        $this->assertSame(UserRole::Administrador, $user->role);
        $this->assertTrue(Hash::check('segredo-forte', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_command_promotes_an_existing_user_without_changing_the_password(): void
    {
        $user = User::factory()->create(['email' => 'operador@example.com']);
        $passwordHash = $user->password;

        $this->artisan('conciliation:create-administrator', ['name' => 'Outro Nome', 'email' => 'operador@example.com'])
            ->expectsOutputToContain(__('conciliation.users.administrator_promoted', ['email' => 'operador@example.com']))
            ->assertSuccessful();

        $user->refresh();

        $this->assertSame(UserRole::Administrador, $user->role);
        $this->assertSame($passwordHash, $user->password);
        $this->assertSame(1, User::query()->count());
    }

    public function test_command_rejects_a_short_password(): void
    {
        $this->artisan('conciliation:create-administrator', ['name' => 'Ana Lima', 'email' => 'ana@example.com'])
            ->expectsQuestion(__('conciliation.users.password'), 'curta')
            ->expectsOutputToContain(__('conciliation.users.password_too_short'))
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
