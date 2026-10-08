<?php

namespace Tests\Feature\Conciliation;

use App\Enums\UserRole;
use App\Models\ReconciliationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SessionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_is_an_operator_by_default(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame(UserRole::Operador, $user->role);
    }

    public function test_factory_can_create_an_administrator(): void
    {
        $user = User::factory()->administrador()->create()->fresh();

        $this->assertSame(UserRole::Administrador, $user->role);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sessionAbilities(): array
    {
        return [
            'viewAny' => ['viewAny'],
            'view' => ['view'],
            'create' => ['create'],
            'upload' => ['upload'],
            'execute' => ['execute'],
            'reopen' => ['reopen'],
            'delete' => ['delete'],
        ];
    }

    #[DataProvider('sessionAbilities')]
    public function test_both_roles_are_allowed_every_session_ability(string $ability): void
    {
        $session = ReconciliationSession::factory()->create();
        $target = in_array($ability, ['viewAny', 'create'], true) ? ReconciliationSession::class : $session;

        $this->assertTrue(User::factory()->create()->can($ability, $target));
        $this->assertTrue(User::factory()->administrador()->create()->can($ability, $target));
    }

    public function test_session_history_gate_accepts_only_administrators(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->administrador()->create())->allows('view-session-history'));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('view-session-history'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('sessions.index'))->assertRedirect(route('login'));
    }
}
