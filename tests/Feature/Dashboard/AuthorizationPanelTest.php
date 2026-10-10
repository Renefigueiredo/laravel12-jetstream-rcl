<?php

namespace Tests\Feature\Dashboard;

use App\Enums\DifferenceTreatment;
use App\Enums\ImportFileStatus;
use App\Enums\ImportSlot;
use App\Enums\JustificationCategory;
use App\Enums\SkipReason;
use App\Models\AuthorizationEntry;
use App\Models\ReconciliationSkip;
use App\Services\Reconciliation\AuthorizationPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class AuthorizationPanelTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_panel_reads_active_authorizations_of_processed_sessions_only(): void
    {
        $july = $this->processedSession('2026-07-01');
        $fromSpreadsheet = $this->authorization($july, 'PADARIA PERNAMBUCANA', 10000);
        $created = AuthorizationEntry::factory()->create([
            'import_file_id' => null,
            'reconciliation_session_id' => $july->id,
            'supplier_name' => 'POSTO ALFA',
            'amount_cents' => 7000,
            'created_by' => $this->administrator()->id,
        ]);
        $duplicate = $this->authorization($july, 'GRAFICA SUL', 20000);
        ReconciliationSkip::query()->create([
            'reconciliation_run_id' => $july->runs()->sole()->id,
            'authorization_entry_id' => $duplicate->id,
            'reason' => SkipReason::DuplicateOfOtherPeriod,
        ]);
        $replaced = $this->authorization($july, 'PAPELARIA CENTRAL', 30000);
        $replacedFile = $this->fileOf($july, ImportSlot::Authorizations)->replicate();
        $replacedFile->status = ImportFileStatus::Replaced;
        $replacedFile->save();
        $replaced->update(['import_file_id' => $replacedFile->id]);

        $this->authorization($this->sessionWithFiles('2026-08-01', state: 'open'), 'SESSAO ABERTA', 40000);
        $this->authorization($this->sessionWithFiles('2026-09-01', state: 'processing'), 'SESSAO EM PROCESSAMENTO', 50000);
        $this->authorization($this->sessionWithFiles('2026-10-01', state: 'reopened'), 'SESSAO REABERTA', 60000);

        $panel = app(AuthorizationPanel::class);

        $this->assertEqualsCanonicalizing([$fromSpreadsheet->id, $created->id], $panel->query()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$fromSpreadsheet->id, $created->id], $panel->open()->pluck('id')->all());
        $this->assertSame([], $panel->reconciled()->pluck('id')->all());
        $this->assertSame(17000, $panel->totals()['balance_cents']);
        $this->assertSame(2, $panel->totals()['open']);
    }

    public function test_totals_add_up_every_processed_session(): void
    {
        $july = $this->processedSession('2026-07-01');
        $august = $this->processedSession('2026-08-01');

        $inInstalments = $this->authorization($july, 'GRAFICA SUL', 90000);
        $this->linkedPayment($july, $inInstalments, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);
        $this->linkedPayment($august, $inInstalments, 30000, link: ['treatment' => DifferenceTreatment::StillOwed]);

        $exact = $this->authorization($july, 'PADARIA PERNAMBUCANA', 10000);
        $this->linkedPayment($july, $exact, 10000);

        $withDiscount = $this->authorization($august, 'PAPELARIA CENTRAL', 100000);
        $this->linkedPayment($august, $withDiscount, 90000, link: [
            'treatment' => DifferenceTreatment::Discount,
            'justificationCategory' => JustificationCategory::CommercialDiscount,
            'justification' => 'Desconto de 10% negociado.',
        ]);

        $withSurcharge = $this->authorization($august, 'POSTO ALFA', 50000);
        $this->linkedPayment($august, $withSurcharge, 52000, link: [
            'treatment' => DifferenceTreatment::AcceptedSurcharge,
            'justificationCategory' => JustificationCategory::Freight,
            'justification' => 'Frete.',
        ]);

        $overpaid = $this->authorization($august, 'RESTAURANTE SABOR', 20000);
        $this->linkedPayment($august, $overpaid, 30000, link: ['treatment' => DifferenceTreatment::Overpayment]);

        $untouched = $this->authorization($august, 'LIVRARIA CULTURA', 40000);

        $panel = app(AuthorizationPanel::class);

        $this->assertSame([
            'authorizations' => 6,
            'authorized_cents' => 310000,
            'paid_cents' => 242000,
            'balance_cents' => 70000,
            'discount_cents' => 10000,
            'accepted_surcharge_cents' => 2000,
            'overpayment_cents' => 10000,
            'open' => 1,
            'partial' => 1,
            'reconciled' => 4,
        ], $panel->totals());

        $this->assertEqualsCanonicalizing([$inInstalments->id, $untouched->id], $panel->open()->pluck('id')->all());
        $this->assertCount(4, $panel->reconciled()->get());

        $filtered = $panel->totals($panel->query()->where('supplier_name', 'GRAFICA SUL'));

        $this->assertSame(1, $filtered['authorizations']);
        $this->assertSame(90000, $filtered['authorized_cents']);
        $this->assertSame(60000, $filtered['paid_cents']);
        $this->assertSame(30000, $filtered['balance_cents']);
        $this->assertSame(0, $filtered['overpayment_cents']);
    }

    public function test_totals_are_zero_without_a_processed_session(): void
    {
        $this->authorization($this->sessionWithFiles(state: 'open'), 'PADARIA PERNAMBUCANA', 10000);

        $panel = app(AuthorizationPanel::class);

        $this->assertFalse($panel->hasProcessedSession());
        $this->assertSame(0, array_sum($panel->totals()));
    }
}
