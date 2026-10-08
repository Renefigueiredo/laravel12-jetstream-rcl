<?php

namespace Tests\Feature\Conciliation;

use App\Enums\UserPermission;
use App\Models\User;
use App\Models\UserPermissionGrant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_has_every_permission_by_role(): void
    {
        $administrator = User::factory()->administrador()->create();

        $this->assertTrue($administrator->hasPermission(UserPermission::ManageExcludedCodes));
        $this->assertSame(0, UserPermissionGrant::query()->count());
    }

    public function test_operator_has_no_permission_by_default(): void
    {
        $operator = User::factory()->create();

        $this->assertFalse($operator->hasPermission(UserPermission::ManageExcludedCodes));
        $this->assertFalse(Gate::forUser($operator)->allows('manage-excluded-codes'));
    }

    public function test_operator_with_a_grant_has_the_permission(): void
    {
        $operator = User::factory()->withPermission(UserPermission::ManageExcludedCodes)->create();

        $this->assertTrue($operator->hasPermission(UserPermission::ManageExcludedCodes));
        $this->assertTrue(Gate::forUser($operator)->allows('manage-excluded-codes'));
        $this->assertSame(UserPermission::ManageExcludedCodes, $operator->permissionGrants()->sole()->permission);
    }

    public function test_a_grant_belongs_only_to_its_user(): void
    {
        User::factory()->withPermission(UserPermission::ManageExcludedCodes)->create();
        $other = User::factory()->create();

        $this->assertFalse($other->hasPermission(UserPermission::ManageExcludedCodes));
    }

    public function test_the_same_permission_cannot_be_granted_twice_to_a_user(): void
    {
        $grant = UserPermissionGrant::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        UserPermissionGrant::factory()->create([
            'user_id' => $grant->user_id,
            'permission' => $grant->permission,
        ]);
    }
}
