<?php

namespace Tests\Feature\Conciliation;

use App\Actions\Conciliation\AddExcludedCode;
use App\Actions\Conciliation\RemoveExcludedCode;
use App\Enums\AuditAction;
use App\Livewire\ExcludedCodes\Index;
use App\Models\AuditLog;
use App\Models\ExcludedOperationCode;
use App\Models\User;
use App\Services\ExcludedCodes\ExcludedOperationCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExcludedCodeListTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrador()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function code(string $code, ?string $description = null, string $createdAt = '2026-07-01 12:00:00', array $attributes = []): ExcludedOperationCode
    {
        return ExcludedOperationCode::factory()->create([
            'code' => $code,
            'description' => $description,
            'created_at' => $createdAt,
            'created_by' => $this->administrator->id,
            ...$attributes,
        ]);
    }

    public function test_list_is_paginated(): void
    {
        $codes = collect(range(1, 60))->map(fn (int $index): ExcludedOperationCode => $this->code(
            (string) (10000000 + $index),
            'Operação '.$index,
            now()->subMinutes($index)->toDateTimeString(),
        ));

        Livewire::actingAs($this->administrator)
            ->test(Index::class)
            ->assertCountTableRecords(60)
            ->assertCanSeeTableRecords($codes->take(25))
            ->assertCanNotSeeTableRecords($codes->skip(25));
    }

    public function test_search_matches_code_and_description(): void
    {
        $payroll = $this->code('20150652', 'Folha de pagamento');
        $agreement = $this->code('11018953', 'Convênio de reciprocidade');
        $tax = $this->code('77700001', 'Imposto retido');

        $screen = Livewire::actingAs($this->administrator)->test(Index::class);

        $screen->searchTable('folha')
            ->assertCanSeeTableRecords([$payroll])
            ->assertCanNotSeeTableRecords([$agreement, $tax]);

        $screen->searchTable('1018')
            ->assertCanSeeTableRecords([$agreement])
            ->assertCanNotSeeTableRecords([$payroll, $tax]);

        $screen->searchTable('')
            ->assertCanSeeTableRecords([$payroll, $agreement, $tax]);
    }

    public function test_sorting_applies_to_the_whole_list(): void
    {
        $codes = collect(range(1, 30))->map(fn (int $index): ExcludedOperationCode => $this->code(
            (string) (10000000 + $index),
            'Descrição '.str_pad((string) (31 - $index), 2, '0', STR_PAD_LEFT),
            now()->subDays($index)->toDateTimeString(),
        ));

        $screen = Livewire::actingAs($this->administrator)->test(Index::class);

        $screen->assertCanSeeTableRecords([$codes[0], $codes[1]], inOrder: true)
            ->assertCanNotSeeTableRecords([$codes[29]]);

        $screen->sortTable('code')
            ->assertCanSeeTableRecords([$codes[0], $codes[1]], inOrder: true)
            ->assertCanNotSeeTableRecords([$codes[29]]);

        $screen->sortTable('code', 'desc')
            ->assertCanSeeTableRecords([$codes[29], $codes[28]], inOrder: true)
            ->assertCanNotSeeTableRecords([$codes[0]]);

        $screen->sortTable('description')
            ->assertCanSeeTableRecords([$codes[29], $codes[28]], inOrder: true)
            ->assertCanNotSeeTableRecords([$codes[0]]);

        $screen->sortTable('created_at')
            ->assertCanSeeTableRecords([$codes[29], $codes[28]], inOrder: true)
            ->assertCanNotSeeTableRecords([$codes[0]]);
    }

    public function test_search_and_sort_are_kept_in_the_url(): void
    {
        $payrollB = $this->code('20150653', 'Folha complementar');
        $payrollA = $this->code('20150652', 'Folha de pagamento');
        $agreement = $this->code('11018953', 'Convênio de reciprocidade');

        Livewire::actingAs($this->administrator)
            ->withQueryParams(['busca' => 'folha', 'ordem' => 'code:asc'])
            ->test(Index::class)
            ->assertSet('tableSearch', 'folha')
            ->assertSet('tableSort', 'code:asc')
            ->assertCanSeeTableRecords([$payrollA, $payrollB], inOrder: true)
            ->assertCanNotSeeTableRecords([$agreement]);
    }

    public function test_removing_a_code_takes_it_off_the_list(): void
    {
        $this->travelTo('2026-07-10 17:20:30');
        $code = $this->code('20150652', 'Folha de pagamento', '2026-07-01 12:00:00');
        $kept = $this->code('11018953');

        Livewire::actingAs($this->administrator)
            ->test(Index::class)
            ->callTableAction('remove', $code)
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.excluded_codes.remove.done', ['code' => '20150652']))
            ->assertCanNotSeeTableRecords([$code])
            ->assertCanSeeTableRecords([$kept]);

        $this->assertModelMissing($code);
        $this->assertSame(['11018953'], app(ExcludedOperationCodes::class)->snapshot()->codes());

        $log = AuditLog::query()->sole();

        $this->assertSame(AuditAction::ExcludedCodeRemoved, $log->action);
        $this->assertSame('Exclusão', $log->action->label());
        $this->assertTrue($log->user->is($this->administrator));
        $this->assertSame('excluded_operation_code', $log->auditable_type);
        $this->assertSame($code->id, $log->auditable_id);
        $this->assertSame('20150652', $log->label);
        $this->assertSame('20150652', $log->before['code']);
        $this->assertSame('Folha de pagamento', $log->before['description']);
        $this->assertSame('manual', $log->before['source']);
        $this->assertNull($log->after);
        $this->assertSame('2026-07-10 17:20:30', $log->created_at->format('Y-m-d H:i:s'));
    }

    public function test_a_removed_code_can_be_added_again_as_a_new_record(): void
    {
        $code = $this->code('20150652', 'Folha de pagamento');

        app(RemoveExcludedCode::class)->handle($this->administrator, $code->id);
        $added = app(AddExcludedCode::class)->handle($this->administrator, '20150652', 'Folha, de novo');

        $this->assertNotSame($code->id, $added->id);
        $this->assertSame('Folha, de novo', ExcludedOperationCode::query()->sole()->description);
        $this->assertSame(
            [AuditAction::ExcludedCodeRemoved, AuditAction::ExcludedCodeAdded],
            AuditLog::query()->orderBy('id')->get()->map->action->all(),
        );
    }

    public function test_removing_a_code_already_removed_shows_a_notice(): void
    {
        $code = $this->code('20150652');
        $screen = Livewire::actingAs($this->administrator)->test(Index::class);

        app(RemoveExcludedCode::class)->handle($this->administrator, $code->id);

        $screen->call('removeCode', $code->id)
            ->assertOk()
            ->assertSet('showingRefusal', true)
            ->assertSee(__('conciliation.excluded_codes.remove.already_removed'));

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_user_without_the_permission_cannot_remove(): void
    {
        $code = $this->code('20150652');

        try {
            app(RemoveExcludedCode::class)->handle(User::factory()->create(), $code->id);
            $this->fail('The removal should have been denied.');
        } catch (AuthorizationException) {
            $this->assertModelExists($code);
        }
    }
}
