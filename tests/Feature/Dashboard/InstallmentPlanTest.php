<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\RemoveInstallmentPlan;
use App\Actions\Conciliation\SaveInstallmentPlan;
use App\Enums\AuditAction;
use App\Enums\DifferenceTreatment;
use App\Enums\ImportFileStatus;
use App\Enums\ImportSlot;
use App\Livewire\Dashboard\AuthorizationsTable;
use App\Models\AuditLog;
use App\Models\ImportFile;
use App\Models\InstallmentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class InstallmentPlanTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    /**
     * @param  list<int>  $amounts
     * @return list<array{amount_cents: int}>
     */
    protected function installments(array $amounts): array
    {
        return array_map(fn (int $cents): array => ['amount_cents' => $cents], $amounts);
    }

    public function test_plan_is_saved_changed_and_removed_with_audit(): void
    {
        $this->travelTo('2026-10-09 15:00:00');
        $july = $this->processedSession('2026-07-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000, ['payment_condition' => 'A vista']);
        $payment = $this->linkedPayment($july, $purchase, 40000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $operator = $this->operator();
        $administrator = $this->administrator();

        $plan = app(SaveInstallmentPlan::class)->handle($operator, $purchase, [
            ['amount_cents' => 40000, 'expected_month' => '2026-07'],
            ['amount_cents' => 30000, 'expected_month' => '2026-08-15'],
            ['amount_cents' => 30000],
        ]);

        $this->assertSame($purchase->identity_key, $plan->authorization_identity_key);
        $this->assertSame($operator->id, $plan->created_by);
        $this->assertSame([
            ['amount_cents' => 40000, 'expected_month' => '2026-07-01'],
            ['amount_cents' => 30000, 'expected_month' => '2026-08-01'],
            ['amount_cents' => 30000, 'expected_month' => null],
        ], $plan->toSchedule());
        $this->assertTrue($purchase->refresh()->plan->is($plan));
        $this->assertSame(60000, $purchase->balanceCents());
        $this->assertSame($purchase->id, $payment->refresh()->link->authorization_entry_id);

        $saved = AuditLog::query()->where('action', AuditAction::InstallmentPlanSaved)->sole();

        $this->assertTrue($saved->user->is($operator));
        $this->assertNull($saved->before);
        $this->assertSame([40000, 30000, 30000], array_column($saved->after['installments'], 'amount_cents'));
        $this->assertSame('2026-10-09 15:00:00', $saved->created_at->format('Y-m-d H:i:s'));

        app(SaveInstallmentPlan::class)->handle($administrator, $purchase, $this->installments([40000, 60000]));

        $changed = AuditLog::query()->where('action', AuditAction::InstallmentPlanSaved)->latest('id')->first();

        $this->assertSame(1, InstallmentPlan::query()->count());
        $this->assertSame($administrator->id, $plan->refresh()->updated_by);
        $this->assertSame([40000, 30000, 30000], array_column($changed->before['installments'], 'amount_cents'));
        $this->assertSame([40000, 60000], array_column($changed->after['installments'], 'amount_cents'));
        $this->assertSame(60000, $purchase->refresh()->balanceCents());

        app(RemoveInstallmentPlan::class)->handle($operator, $purchase);

        $removed = AuditLog::query()->where('action', AuditAction::InstallmentPlanRemoved)->sole();

        $this->assertSame(0, InstallmentPlan::query()->count());
        $this->assertNull($purchase->refresh()->plan);
        $this->assertSame([40000, 60000], array_column($removed->before['installments'], 'amount_cents'));
        $this->assertNull($removed->after);
        $this->assertNotNull($payment->refresh()->link);
    }

    public function test_invalid_plans_are_refused_with_the_reason(): void
    {
        $july = $this->processedSession('2026-07-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000);
        $this->linkedPayment($july, $purchase, 20000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $this->linkedPayment($july, $purchase, 20000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $settled = $this->authorization($july, 'PADARIA PERNAMBUCANA', 10000);
        $this->linkedPayment($july, $settled, 10000);
        $operator = $this->operator();

        foreach ([
            [$purchase, [], __('conciliation.dashboard.plan.errors.count', ['max' => 120])],
            [$purchase, $this->installments(array_fill(0, 121, 1000)), __('conciliation.dashboard.plan.errors.count', ['max' => 120])],
            [$purchase, $this->installments([100000, 0]), __('conciliation.dashboard.plan.errors.amount')],
            [$purchase, $this->installments([110000, -10000]), __('conciliation.dashboard.plan.errors.amount')],
            [$purchase, [['amount_cents' => 100000, 'expected_month' => '08/2026']], __('conciliation.dashboard.plan.errors.month')],
            [$purchase, [['amount_cents' => 100000, 'expected_month' => '2026-13']], __('conciliation.dashboard.plan.errors.month')],
            [$purchase, $this->installments([40000, 30000, 20000]), __('conciliation.dashboard.plan.errors.sum', ['difference' => 'R$ 100,00', 'direction' => 'abaixo'])],
            [$purchase, $this->installments([40000, 30000, 30051]), __('conciliation.dashboard.plan.errors.sum', ['difference' => 'R$ 0,51', 'direction' => 'acima'])],
            [$purchase, $this->installments([100000]), trans_choice('conciliation.dashboard.plan.errors.fewer_than_paid', 2, ['count' => 2])],
            [$settled, $this->installments([5000, 5000]), __('conciliation.dashboard.plan.errors.settled')],
        ] as [$authorization, $installments, $message]) {
            try {
                app(SaveInstallmentPlan::class)->handle($operator, $authorization, $installments);
                $this->fail('Accepted: '.$message);
            } catch (ActionRefusedException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }

        app(SaveInstallmentPlan::class)->handle($operator, $purchase, $this->installments([40000, 30000, 30050]));

        $this->assertSame(1, InstallmentPlan::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::InstallmentPlanSaved)->count());

        try {
            app(RemoveInstallmentPlan::class)->handle($operator, $settled);
            $this->fail('Removed a plan that does not exist.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.dashboard.plan.errors.missing'), $exception->getMessage());
        }
    }

    public function test_plan_follows_the_authorization_when_the_spreadsheet_is_replaced(): void
    {
        $july = $this->processedSession('2026-07-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000);
        app(SaveInstallmentPlan::class)->handle($this->operator(), $purchase, $this->installments([40000, 30000, 30000]));

        $oldFile = $this->fileOf($july, ImportSlot::Authorizations);
        $oldFile->update(['status' => ImportFileStatus::Replaced]);
        $newFile = ImportFile::factory()->forSlot(ImportSlot::Authorizations)->create(['reconciliation_session_id' => $july->id]);
        $again = $purchase->replicate()->fill(['import_file_id' => $newFile->id]);
        $again->save();

        $this->assertNotSame($purchase->id, $again->id);
        $this->assertSame([40000, 30000, 30000], array_column($again->refresh()->plan->toSchedule(), 'amount_cents'));
    }

    public function test_plan_is_informed_and_removed_on_the_screen(): void
    {
        $july = $this->processedSession('2026-07-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000);
        $this->linkedPayment($july, $purchase, 40000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $settled = $this->authorization($july, 'PADARIA PERNAMBUCANA', 10000);
        $this->linkedPayment($july, $settled, 10000);
        $operator = $this->operator();

        Livewire::actingAs($operator)
            ->test(AuthorizationsTable::class, ['scope' => 'reconciled'])
            ->assertTableActionHidden('installmentPlan', $settled)
            ->assertTableActionHidden('removeInstallmentPlan', $settled);

        $screen = Livewire::actingAs($operator)->test(AuthorizationsTable::class, ['scope' => 'open']);

        $screen->assertTableActionVisible('installmentPlan', $purchase)
            ->assertTableActionHidden('removeInstallmentPlan', $purchase)
            ->mountTableAction('installmentPlan', $purchase)
            ->setTableActionData(['installments' => [['amount' => 'quatrocentos', 'month' => '13/2026']]])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['installments.0.amount', 'installments.0.month']);

        $screen->setTableActionData(['installments' => [['amount' => '400,00', 'month' => ''], ['amount' => '500,00', 'month' => '']]])
            ->callMountedTableAction()
            ->assertSet('showingRefusal', true)
            ->assertSee(__('conciliation.dashboard.plan.errors.sum', ['difference' => 'R$ 100,00', 'direction' => 'abaixo']));

        $this->assertSame(0, InstallmentPlan::query()->count());

        Livewire::actingAs($operator)
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->mountTableAction('installmentPlan', $purchase)
            ->setTableActionData(['installments' => [
                ['amount' => '400,00', 'month' => '07/2026'],
                ['amount' => '300,00', 'month' => '09/2026'],
                ['amount' => '300', 'month' => ''],
            ]])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertSet('showingRefusal', false)
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.dashboard.plan.saved'))
            ->assertSee([
                __('conciliation.dashboard.forecast.heading'),
                __('conciliation.dashboard.forecast.from_plan'),
                __('conciliation.dashboard.forecast.installment', ['position' => 1]),
                __('conciliation.dashboard.forecast.installment', ['position' => 2]),
                __('conciliation.dashboard.forecast.expected_in', ['month' => '09/2026']),
            ]);

        $this->assertSame([
            ['amount_cents' => 40000, 'expected_month' => '2026-07-01'],
            ['amount_cents' => 30000, 'expected_month' => '2026-09-01'],
            ['amount_cents' => 30000, 'expected_month' => null],
        ], $purchase->refresh()->plan->toSchedule());

        $again = Livewire::actingAs($this->administrator())
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->assertTableActionVisible('removeInstallmentPlan', $purchase)
            ->mountTableAction('installmentPlan', $purchase);

        $this->assertSame(
            [['400,00', '07/2026'], ['300,00', '09/2026'], ['300,00', '']],
            array_map(fn (array $item): array => [$item['amount'], $item['month']], array_values($again->instance()->mountedActions[0]['data']['installments'])),
        );

        $again->unmountAction()
            ->callTableAction('removeInstallmentPlan', $purchase)
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.dashboard.plan.removed'));

        $this->assertNull($purchase->refresh()->plan);
    }

    public function test_a_payment_outside_the_plan_is_pointed_out_in_the_statement(): void
    {
        $july = $this->processedSession('2026-07-01');
        $purchase = $this->authorization($july, 'GRAFICA SUL', 100000);
        $this->linkedPayment($july, $purchase, 25000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        app(SaveInstallmentPlan::class)->handle($this->operator(), $purchase, $this->installments([40000, 30000, 30000]));

        Livewire::actingAs($this->operator())
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->assertSee(__('conciliation.dashboard.forecast.out_of_plan'))
            ->assertSee(__('conciliation.dashboard.forecast.installment', ['position' => 3]));
    }
}
