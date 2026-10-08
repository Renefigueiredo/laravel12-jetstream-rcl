<?php

namespace Tests\Feature\Conciliation;

use App\Enums\AuditAction;
use App\Enums\UserPermission;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPermissionGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionCommandTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrador()->create(['email' => 'admin@example.com']);
        $this->operator = User::factory()->create(['email' => 'operador@example.com']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function arguments(array $overrides = []): array
    {
        return [
            'email' => 'operador@example.com',
            'permission' => 'manage_excluded_codes',
            '--by' => 'admin@example.com',
            ...$overrides,
        ];
    }

    public function test_grant_gives_the_permission_and_is_audited(): void
    {
        $this->artisan('conciliation:grant-permission', $this->arguments())
            ->expectsOutputToContain('operador@example.com')
            ->assertSuccessful();

        $grant = UserPermissionGrant::query()->sole();

        $this->assertTrue($this->operator->hasPermission(UserPermission::ManageExcludedCodes));
        $this->assertTrue($grant->user->is($this->operator));
        $this->assertTrue($grant->grantor->is($this->administrator));

        $log = AuditLog::query()->sole();

        $this->assertSame(AuditAction::PermissionGranted, $log->action);
        $this->assertTrue($log->user->is($this->administrator));
        $this->assertSame('user_permission', $log->auditable_type);
        $this->assertSame($grant->id, $log->auditable_id);
        $this->assertSame('operador@example.com', $log->label);
        $this->assertNull($log->before);
        $this->assertSame(['user_id' => $this->operator->id, 'email' => 'operador@example.com', 'permission' => 'manage_excluded_codes'], $log->after);
    }

    public function test_granting_again_changes_nothing(): void
    {
        $this->artisan('conciliation:grant-permission', $this->arguments())->assertSuccessful();
        $this->artisan('conciliation:grant-permission', $this->arguments())->assertSuccessful();

        $this->assertSame(1, UserPermissionGrant::query()->count());
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_grant_fails_without_a_responsible_administrator(): void
    {
        $this->artisan('conciliation:grant-permission', $this->arguments(['--by' => null]))->assertFailed();
        $this->artisan('conciliation:grant-permission', $this->arguments(['--by' => 'ninguem@example.com']))->assertFailed();
        $this->artisan('conciliation:grant-permission', $this->arguments(['--by' => 'operador@example.com']))->assertFailed();

        $this->assertSame(0, UserPermissionGrant::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_grant_fails_for_unknown_user_or_permission(): void
    {
        $this->artisan('conciliation:grant-permission', $this->arguments(['email' => 'ninguem@example.com']))->assertFailed();
        $this->artisan('conciliation:grant-permission', $this->arguments(['permission' => 'voar']))
            ->expectsOutputToContain('manage_excluded_codes')
            ->assertFailed();

        $this->assertSame(0, UserPermissionGrant::query()->count());
    }

    public function test_revoke_removes_the_permission_and_is_audited(): void
    {
        $this->artisan('conciliation:grant-permission', $this->arguments())->assertSuccessful();
        $grantId = UserPermissionGrant::query()->sole()->id;

        $this->artisan('conciliation:revoke-permission', $this->arguments())->assertSuccessful();

        $this->assertFalse($this->operator->hasPermission(UserPermission::ManageExcludedCodes));
        $this->assertSame(0, UserPermissionGrant::query()->count());

        $log = AuditLog::query()->where('action', AuditAction::PermissionRevoked)->sole();

        $this->assertTrue($log->user->is($this->administrator));
        $this->assertSame($grantId, $log->auditable_id);
        $this->assertSame('operador@example.com', $log->label);
        $this->assertSame(['user_id' => $this->operator->id, 'email' => 'operador@example.com', 'permission' => 'manage_excluded_codes'], $log->before);
        $this->assertNull($log->after);
    }

    public function test_revoking_what_was_not_granted_is_not_an_error(): void
    {
        $this->artisan('conciliation:revoke-permission', $this->arguments())->assertSuccessful();

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_revoke_fails_without_a_responsible_administrator(): void
    {
        UserPermissionGrant::factory()->create(['user_id' => $this->operator->id]);

        $this->artisan('conciliation:revoke-permission', $this->arguments(['--by' => 'operador@example.com']))->assertFailed();
        $this->artisan('conciliation:revoke-permission', $this->arguments(['--by' => null]))->assertFailed();

        $this->assertSame(1, UserPermissionGrant::query()->count());
    }
}
