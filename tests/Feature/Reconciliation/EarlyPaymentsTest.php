<?php

namespace Tests\Feature\Reconciliation;

use App\Enums\LinkOrigin;
use App\Livewire\Reconciliation\EarlyPaymentsTable;
use App\Livewire\Reconciliation\LinksTable;
use App\Livewire\Reconciliation\PendingTable;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSettings;
use App\Models\ReconciliationSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class EarlyPaymentsTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_payment_before_the_authorization_is_reconciled_and_shown_in_its_own_tab(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 50000, ['authorized_on' => '2026-07-15']);
        $early = $this->payment($session, 'PADARIA PERNAMBUCANA', 50000, ['paid_on' => '2026-07-10']);
        $this->authorization($session, 'PAPELARIA CENTRAL', 20000, ['authorized_on' => '2026-07-15']);
        $sameDay = $this->payment($session, 'PAPELARIA CENTRAL', 20000, ['paid_on' => '2026-07-15']);
        $this->authorization($session, 'MERCADO BOM PRECO CENTRO', 30000, ['authorized_on' => '2026-07-15']);
        $this->payment($session, 'MERCADO BOM PRXYO', 30000, ['paid_on' => '2026-07-10']);

        $run = $this->reconcile($session);

        $this->assertSame(2, ReconciliationLink::query()->count());
        $this->assertTrue($early->link->paid_before_authorization);
        $this->assertSame(LinkOrigin::Automatic, $early->link->origin);
        $this->assertFalse($sameDay->link->paid_before_authorization);
        $this->assertSame(2, $run->totals['paid_before_authorization']);

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $session->id, 'onlyPaidBefore' => true])
            ->assertCanSeeTableRecords([$early->link])
            ->assertCanNotSeeTableRecords([$sameDay->link])
            ->assertCountTableRecords(1)
            ->assertSee(__('conciliation.reconciliation.warnings.paid_before_authorization'));

        $doubtful = ReconciliationSuggestion::query()->sole();

        $this->assertTrue($doubtful->paid_before_authorization);

        Livewire::actingAs($this->operator())
            ->test(EarlyPaymentsTable::class, ['sessionId' => $session->id])
            ->assertCanSeeTableRecords([$doubtful])
            ->assertCountTableRecords(1)
            ->assertTableColumnFormattedStateSet('status', __('conciliation.reconciliation.early.pending'), $doubtful)
            ->assertTableColumnFormattedStateSet('authorization.authorized_on', '15/07/2026', $doubtful)
            ->assertTableColumnFormattedStateSet('payment.paid_on', '10/07/2026', $doubtful);

        Livewire::actingAs($this->operator())
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->assertSee(__('conciliation.reconciliation.warnings.paid_before_authorization'));
    }

    public function test_difference_within_the_percentage_tolerance_is_reconciled_and_flagged(): void
    {
        $this->useTolerance(50, 100, 20000);
        $session = $this->sessionWithFiles(state: 'open');
        $less = $this->authorization($session, 'FORNECEDOR ALFA', 100000);
        $paidLess = $this->payment($session, 'FORNECEDOR ALFA', 99200);
        $this->authorization($session, 'FORNECEDOR BRAVO', 100000);
        $paidMore = $this->payment($session, 'FORNECEDOR BRAVO', 100600);
        $this->authorization($session, 'FORNECEDOR CHARLIE', 100000);
        $cents = $this->payment($session, 'FORNECEDOR CHARLIE', 100030);
        $this->authorization($session, 'FORNECEDOR DELTA', 100000);
        $beyond = $this->payment($session, 'FORNECEDOR DELTA', 98900);
        $this->authorization($session, 'FORNECEDOR ECHO', 5000000);
        $beyondTheCap = $this->payment($session, 'FORNECEDOR ECHO', 4979900);

        $run = $this->reconcile($session);

        $this->assertSame([100, 20000], [$run->tolerance_basis_points, $run->tolerance_cap_cents]);
        $this->assertSame(-800, $paidLess->link->difference_cents);
        $this->assertSame(800, $paidLess->link->tolerance_writeoff_cents);
        $this->assertSame(0, $less->refresh()->balanceCents());
        $this->assertSame(600, $paidMore->link->difference_cents);
        $this->assertNull($beyond->link);
        $this->assertNull($beyondTheCap->link);
        $this->assertSame(['count' => 2, 'paid_less_cents' => 800, 'paid_more_cents' => 600], $run->totals['with_difference']);

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $session->id])
            ->assertCountTableRecords(3)
            ->assertTableColumnFormattedStateSet('difference_cents', '-R$ 8,00', $paidLess->link)
            ->assertSee(__('conciliation.reconciliation.links.paid_less'))
            ->filterTable('difference', 'with')
            ->assertCanSeeTableRecords([$paidLess->link, $paidMore->link])
            ->assertCanNotSeeTableRecords([$cents->link])
            ->assertCountTableRecords(2)
            ->filterTable('difference', 'more')
            ->assertCanSeeTableRecords([$paidMore->link])
            ->assertCountTableRecords(1);
    }

    public function test_new_installations_start_with_one_percent_capped_at_two_hundred(): void
    {
        ReconciliationSettings::query()->delete();

        $settings = ReconciliationSettings::current();

        $this->assertSame([50, 100, 20000], [$settings->tolerance_cents, $settings->tolerance_basis_points, $settings->tolerance_cap_cents]);
    }
}
