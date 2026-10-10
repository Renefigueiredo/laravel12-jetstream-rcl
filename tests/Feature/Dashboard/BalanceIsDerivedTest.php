<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Conciliation\SaveInstallmentPlan;
use App\Enums\DifferenceTreatment;
use App\Livewire\Dashboard\AuthorizationsTable;
use App\Livewire\Dashboard\DivergencesTable;
use App\Models\AuditLog;
use App\Models\AuthorizationEntry;
use App\Models\AuthorizationState;
use App\Models\PendingItem;
use App\Services\Reconciliation\AuthorizationStateCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class BalanceIsDerivedTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    /**
     * @return array<int, array{links_count: int, paid_cents: int, balance_cents: int}>
     */
    protected function storedStates(): array
    {
        return AuthorizationState::query()->orderBy('authorization_entry_id')->get()
            ->mapWithKeys(fn (AuthorizationState $state): array => [$state->authorization_entry_id => $state->only(['links_count', 'paid_cents', 'balance_cents'])])
            ->all();
    }

    protected function assertBalancesComeFromTheLinks(string $after): void
    {
        $stored = $this->storedStates();

        DB::transaction(function (): void {
            foreach (AuthorizationEntry::query()->pluck('id') as $id) {
                app(AuthorizationStateCalculator::class)->recalculate($id);
            }
        });

        $this->assertSame($stored, $this->storedStates(), 'After: '.$after);
    }

    public function test_every_action_of_the_panel_leaves_balances_derived_from_links_and_audited(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $inInstalments = $this->authorization($session, 'GRAFICA SUL', 90000, ['payment_condition' => '3x']);
        $instalment = $this->payment($session, 'GRAFICA SUL', 30000);
        $travel = $this->authorization($session, 'AGENCIA DE VIAGENS', 45000);
        $airline = $this->payment($session, 'COMPANHIA AEREA', 40000);
        $this->authorization($session, 'RESTAURANTE SABOR', 50000);
        $excess = $this->payment($session, 'RESTAURANTE SABOR', 52000);
        $this->authorization($session, 'PAPELARIA CENTRAL', 10000);
        $rejected = $this->payment($session, 'PAPELARIA CENTRAL', 14000);
        $orphan = $this->payment($session, 'POSTO ALFA', 7000);
        $this->reconcile($session);
        $administrator = $this->administrator();

        $this->assertBalancesComeFromTheLinks('run');

        $divergences = fn () => Livewire::actingAs($administrator)->test(DivergencesTable::class);

        $divergences()->mountTableAction('linkToAuthorization', PendingItem::query()->findOrFail('p-'.$airline->id))
            ->setTableActionData(['authorization' => $travel->id, 'treatment' => DifferenceTreatment::StillOwed->value])
            ->callMountedTableAction();
        $this->assertBalancesComeFromTheLinks('link');

        $divergences()->mountTableAction('confirm', PendingItem::query()->findOrFail('s-'.$excess->suggestions()->sole()->id))
            ->setTableActionData(['treatment' => DifferenceTreatment::Overpayment->value])
            ->callMountedTableAction();
        $this->assertBalancesComeFromTheLinks('confirm');

        $divergences()->callTableAction('reject', PendingItem::query()->findOrFail('s-'.$rejected->suggestions()->sole()->id));
        $this->assertBalancesComeFromTheLinks('reject');

        $divergences()->mountTableAction('createAuthorization', PendingItem::query()->findOrFail('p-'.$orphan->id))
            ->setTableActionData(['justification' => 'Abastecimento de emergência.'])
            ->callMountedTableAction();
        $this->assertBalancesComeFromTheLinks('create');

        app(SaveInstallmentPlan::class)->handle($administrator, $inInstalments, [['amount_cents' => 30000], ['amount_cents' => 60000]]);
        $this->assertBalancesComeFromTheLinks('plan');

        Livewire::actingAs($administrator)
            ->test(AuthorizationsTable::class, ['scope' => 'open'])
            ->callTableAction('removeInstallmentPlan', $inInstalments)
            ->call('undoLink', $instalment->link->id);
        $this->assertBalancesComeFromTheLinks('undo');

        $this->assertSame(90000, $inInstalments->refresh()->balanceCents());
        $this->assertSame(5000, $travel->refresh()->balanceCents());

        $this->assertEqualsCanonicalizing(
            ['manual_link_created', 'suggestion_confirmed', 'suggestion_rejected', 'authorization_created_in_reconciliation', 'installment_plan_saved', 'installment_plan_removed', 'link_removed'],
            AuditLog::query()->where('user_id', $administrator->id)->pluck('action')->map(fn ($action) => $action->value)->all(),
        );
    }
}
