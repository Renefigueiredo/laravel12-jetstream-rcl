<?php

namespace Tests\Feature\Conciliation;

use App\Enums\UserPermission;
use App\Livewire\ExcludedCodes\Index;
use App\Models\ExcludedCodeImport;
use App\Models\ExcludedOperationCode;
use App\Models\User;
use App\Models\UserPermissionGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\BuildsSpreadsheets;
use Tests\TestCase;

class ExcludedCodeAuthorizationTest extends TestCase
{
    use BuildsSpreadsheets;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('excluded-codes.index'))->assertRedirect(route('login'));
    }

    public function test_operator_without_the_permission_is_denied(): void
    {
        $operator = User::factory()->create();

        $this->actingAs($operator)->get(route('excluded-codes.index'))->assertForbidden();

        Livewire::actingAs($operator)->test(Index::class)->assertForbidden();
    }

    public function test_administrator_opens_the_screen(): void
    {
        $this->actingAs(User::factory()->administrador()->create())
            ->get(route('excluded-codes.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class)
            ->assertSee(__('conciliation.excluded_codes.title'));
    }

    public function test_operator_with_the_permission_opens_the_screen(): void
    {
        $operator = User::factory()->withPermission(UserPermission::ManageExcludedCodes)->create();

        $this->actingAs($operator)->get(route('excluded-codes.index'))->assertOk();
    }

    public function test_menu_item_is_shown_only_to_those_with_the_permission(): void
    {
        $this->actingAs(User::factory()->administrador()->create())
            ->get(route('dashboard'))
            ->assertSee(__('conciliation.excluded_codes.nav'));

        $this->actingAs(User::factory()->withPermission(UserPermission::ManageExcludedCodes)->create())
            ->get(route('dashboard'))
            ->assertSee(__('conciliation.excluded_codes.nav'));

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertDontSee(__('conciliation.excluded_codes.nav'));
    }

    /**
     * Open the screen with the permission and take the permission away before the next action.
     *
     * @return Testable<Index>
     */
    protected function openScreenThenRevoke(User $operator): Testable
    {
        UserPermissionGrant::factory()->create(['user_id' => $operator->id]);

        $screen = Livewire::actingAs($operator)->test(Index::class)->assertOk();

        $operator->permissionGrants()->delete();

        return $screen;
    }

    public function test_actions_are_refused_once_the_permission_is_revoked(): void
    {
        $operator = User::factory()->create();
        $code = ExcludedOperationCode::factory()->create();
        $upload = $this->rawFile("20150653\r\n", 'codigos.csv');

        $this->openScreenThenRevoke($operator)->call('openAddModal')->assertForbidden();
        $this->openScreenThenRevoke($operator)->set('form.code', '20150652')->call('save')->assertForbidden();
        $this->openScreenThenRevoke($operator)->call('removeCode', $code->id)->assertForbidden();
        $this->openScreenThenRevoke($operator)->callTableAction('remove', $code)->assertForbidden();
        $this->openScreenThenRevoke($operator)->set('upload', $upload)->assertForbidden();

        $this->assertSame(1, ExcludedOperationCode::query()->count());
        $this->assertSame(0, ExcludedCodeImport::query()->count());
    }
}
