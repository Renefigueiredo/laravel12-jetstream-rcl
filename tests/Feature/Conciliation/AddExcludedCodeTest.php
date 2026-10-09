<?php

namespace Tests\Feature\Conciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\AddExcludedCode;
use App\Enums\AuditAction;
use App\Enums\ExcludedCodeSource;
use App\Livewire\ExcludedCodes\Index;
use App\Models\AuditLog;
use App\Models\ExcludedOperationCode;
use App\Models\PaymentEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddExcludedCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_code_with_description_enters_the_list(): void
    {
        $this->travelTo('2026-07-10 17:20:30');
        $administrator = User::factory()->administrador()->create(['name' => 'Ana Administradora']);

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->call('openAddModal')
            ->assertSet('showingAddModal', true)
            ->set('form.code', '20150652')
            ->set('form.description', 'Folha de pagamento')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showingAddModal', false)
            ->assertSet('form.code', '')
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.excluded_codes.add.done', ['code' => '20150652']));

        $code = ExcludedOperationCode::query()->sole();

        $this->assertSame('20150652', $code->code);
        $this->assertSame('Folha de pagamento', $code->description);
        $this->assertSame(ExcludedCodeSource::Manual, $code->source);
        $this->assertNull($code->excluded_code_import_id);
        $this->assertTrue($code->creator->is($administrator));
        $this->assertSame('2026-07-10 17:20:30', $code->created_at->format('Y-m-d H:i:s'));

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->assertCanSeeTableRecords([$code])
            ->assertTableColumnStateSet('code', '20150652', $code)
            ->assertTableColumnStateSet('description', 'Folha de pagamento', $code)
            ->assertTableColumnStateSet('creator.name', 'Ana Administradora', $code)
            ->assertTableColumnFormattedStateSet('created_at', '10/07/2026 14:20', $code);
    }

    public function test_description_is_optional(): void
    {
        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(Index::class)
            ->set('form.code', '20150652')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(ExcludedOperationCode::query()->sole()->description);
    }

    public function test_surrounding_spaces_are_ignored(): void
    {
        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(Index::class)
            ->set('form.code', ' 20150652 ')
            ->set('form.description', '  Folha de pagamento  ')
            ->call('save')
            ->assertHasNoErrors();

        $code = ExcludedOperationCode::query()->sole();

        $this->assertSame('20150652', $code->code);
        $this->assertSame('Folha de pagamento', $code->description);
    }

    public function test_duplicate_code_is_refused(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '20150652', 'description' => 'Folha']);

        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(Index::class)
            ->set('form.code', ' 20150652 ')
            ->set('form.description', 'Outra descrição')
            ->call('save')
            ->assertHasErrors('form.code')
            ->assertSee(__('conciliation.excluded_codes.errors.duplicate', ['code' => '20150652']))
            ->assertSet('showingAddModal', false);

        $this->assertSame('Folha', ExcludedOperationCode::query()->sole()->description);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_codes_differing_by_leading_zeros_or_case_are_distinct(): void
    {
        $administrator = User::factory()->administrador()->create();

        foreach (['123', '00123', 'abc', 'ABC'] as $code) {
            app(AddExcludedCode::class)->handle($administrator, $code, null);
        }

        $this->assertSame(4, ExcludedOperationCode::query()->count());
    }

    public function test_blank_or_invalid_code_is_not_saved(): void
    {
        $screen = Livewire::actingAs(User::factory()->administrador()->create())->test(Index::class);

        foreach (['', '   ', '12-34', '12 34', 'CÓDIGO', str_repeat('9', 21)] as $code) {
            $screen->set('form.code', $code)->call('save')->assertHasErrors('form.code');
        }

        $screen->set('form.code', str_repeat('9', 20))->call('save')->assertHasNoErrors();

        $this->assertSame(1, ExcludedOperationCode::query()->count());
    }

    public function test_description_is_limited_to_255_characters(): void
    {
        $screen = Livewire::actingAs(User::factory()->administrador()->create())->test(Index::class);

        $screen->set('form.code', '1')->set('form.description', str_repeat('a', 256))->call('save')->assertHasErrors('form.description');
        $screen->set('form.code', '2')->set('form.description', str_repeat('a', 255))->call('save')->assertHasNoErrors();

        $this->assertSame(['2'], ExcludedOperationCode::query()->pluck('code')->all());
    }

    public function test_manual_inclusion_is_audited(): void
    {
        $this->travelTo('2026-07-10 17:20:30');
        $administrator = User::factory()->administrador()->create();

        $code = app(AddExcludedCode::class)->handle($administrator, '20150652', 'Folha de pagamento');

        $log = AuditLog::query()->sole();

        $this->assertSame(AuditAction::ExcludedCodeAdded, $log->action);
        $this->assertSame('Inclusão manual', $log->action->label());
        $this->assertTrue($log->user->is($administrator));
        $this->assertSame('excluded_operation_code', $log->auditable_type);
        $this->assertSame($code->id, $log->auditable_id);
        $this->assertSame('20150652', $log->label);
        $this->assertNull($log->before);
        $this->assertSame(['code' => '20150652', 'description' => 'Folha de pagamento'], $log->after);
        $this->assertSame('2026-07-10 17:20:30', $log->created_at->format('Y-m-d H:i:s'));
    }

    public function test_action_refuses_invalid_and_duplicate_codes(): void
    {
        $administrator = User::factory()->administrador()->create();
        $action = app(AddExcludedCode::class);

        $action->handle($administrator, '20150652', null);

        foreach (['20150652', '12-34', ''] as $code) {
            try {
                $action->handle($administrator, $code, null);
                $this->fail('The code "'.$code.'" should have been refused.');
            } catch (ActionRefusedException) {
                $this->assertSame(1, ExcludedOperationCode::query()->count());
            }
        }
    }

    public function test_code_used_by_no_imported_payment_gets_a_warning_that_does_not_block(): void
    {
        PaymentEntry::factory()->create(['operation_code' => '11001724']);
        $warning = __('conciliation.excluded_codes.unused_warning', ['code' => '1100172']);

        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(Index::class)
            ->call('openAddModal')
            ->assertDontSee($warning)
            ->set('form.code', ' 1100172 ')
            ->assertSee($warning)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showingAddModal', false)
            ->assertDispatched('banner-message', style: 'warning', message: __('conciliation.excluded_codes.add.done_unused', ['code' => '1100172']));

        $this->assertSame('1100172', ExcludedOperationCode::query()->sole()->code);
    }

    public function test_code_used_by_an_imported_payment_gets_no_warning(): void
    {
        PaymentEntry::factory()->create(['operation_code' => '11001724']);

        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(Index::class)
            ->set('form.code', '11001724')
            ->assertSet('codeUsageWarning', null)
            ->call('save')
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.excluded_codes.add.done', ['code' => '11001724']));
    }

    public function test_no_warning_while_no_payment_was_imported(): void
    {
        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(Index::class)
            ->set('form.code', '1100172')
            ->assertSet('codeUsageWarning', null)
            ->call('save')
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.excluded_codes.add.done', ['code' => '1100172']));
    }

    public function test_no_usage_warning_for_blank_invalid_or_already_listed_codes(): void
    {
        PaymentEntry::factory()->create(['operation_code' => '11001724']);
        ExcludedOperationCode::factory()->create(['code' => '555']);

        $screen = Livewire::actingAs(User::factory()->administrador()->create())->test(Index::class);

        foreach (['', '   ', '12-34', '555'] as $code) {
            $screen->set('form.code', $code)->assertSet('codeUsageWarning', null);
        }
    }

    public function test_the_name_the_erp_gives_the_code_is_shown_while_typing(): void
    {
        PaymentEntry::factory()->count(3)->create(['operation_code' => '11052094', 'operation_name' => 'MATERIAL DE CONSTRUÇÃO E MANUTENÇÃO']);
        PaymentEntry::factory()->create(['operation_code' => '11052094', 'operation_name' => 'MATERIAL DE CONSTRUCAO']);
        PaymentEntry::factory()->create(['operation_code' => '11051039', 'operation_name' => 'PRODUÇÃO MÉDICA']);

        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(Index::class)
            ->call('openAddModal')
            ->assertSet('codeUsage', null)
            ->assertDontSee(__('conciliation.excluded_codes.erp.heading'))
            ->set('form.code', ' 11052094 ')
            ->set('form.description', 'Produção Médica')
            ->assertSet('codeUsage', ['name' => 'MATERIAL DE CONSTRUÇÃO E MANUTENÇÃO', 'payments' => 4])
            ->assertSee(__('conciliation.excluded_codes.erp.heading'))
            ->assertSee('MATERIAL DE CONSTRUÇÃO E MANUTENÇÃO')
            ->assertSee(trans_choice('conciliation.excluded_codes.erp.payments', 4, ['count' => 4]))
            ->assertSet('codeUsageWarning', null)
            ->call('useErpNameAsDescription')
            ->assertSet('form.description', 'MATERIAL DE CONSTRUÇÃO E MANUTENÇÃO')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('MATERIAL DE CONSTRUÇÃO E MANUTENÇÃO', ExcludedOperationCode::query()->sole()->description);
    }

    public function test_no_erp_name_for_codes_no_payment_carries_or_that_are_invalid(): void
    {
        PaymentEntry::factory()->create(['operation_code' => '11052094', 'operation_name' => null]);

        $screen = Livewire::actingAs(User::factory()->administrador()->create())->test(Index::class);

        foreach (['', '12-34', '99999999'] as $code) {
            $screen->set('form.code', $code)->assertSet('codeUsage', null);
        }

        $screen->set('form.code', '11052094')
            ->assertSet('codeUsage', ['name' => null, 'payments' => 1])
            ->assertSee(__('conciliation.excluded_codes.erp.unnamed'))
            ->assertDontSee(__('conciliation.excluded_codes.erp.use_as_description'))
            ->call('useErpNameAsDescription')
            ->assertSet('form.description', '');
    }

    public function test_user_without_the_permission_cannot_add(): void
    {
        $this->expectException(AuthorizationException::class);

        app(AddExcludedCode::class)->handle(User::factory()->create(), '20150652', null);
    }
}
