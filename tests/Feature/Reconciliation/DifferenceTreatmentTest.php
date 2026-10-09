<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\CloseAuthorizationWithDiscount;
use App\Actions\Conciliation\ConfirmSuggestion;
use App\Actions\Conciliation\RemoveLink;
use App\Enums\AuditAction;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\JustificationCategory;
use App\Models\AuditLog;
use App\Models\AuthorizationEntry;
use App\Models\PendingItem;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationPairBlock;
use App\Models\ReconciliationSession;
use App\Models\ReconciliationSettings;
use App\Models\ReconciliationSuggestion;
use App\Services\Reconciliation\DifferenceDecision;
use App\Services\Reconciliation\RunTotals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class DifferenceTreatmentTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    /**
     * @return array{0: ReconciliationSession, 1: AuthorizationEntry, 2: ReconciliationSuggestion}
     */
    protected function suggested(int $authorized, int $paid): array
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'CONSTRUTORA HORIZONTE', $authorized);
        $this->payment($session, 'CONSTRUTORA HORIZONTE', $paid);
        $this->reconcile($session);

        return [$session, $authorization, ReconciliationSuggestion::query()->sole()];
    }

    protected function decision(DifferenceTreatment $treatment, bool $justified = false): DifferenceDecision
    {
        return $justified
            ? new DifferenceDecision($treatment, JustificationCategory::CommercialDiscount, 'Negociado com o fornecedor')
            : new DifferenceDecision($treatment);
    }

    public function test_partial_confirmed_as_still_owed_keeps_the_authorization_open(): void
    {
        [, $authorization, $suggestion] = $this->suggested(300000, 100000);

        $link = app(ConfirmSuggestion::class)->handle($this->operator(), $suggestion, $this->decision(DifferenceTreatment::StillOwed));

        $this->assertSame(DifferenceTreatment::StillOwed, $link->treatment);
        $this->assertSame(AuthorizationStatus::Partial, $authorization->refresh()->status());
        $this->assertSame(200000, $authorization->balanceCents());
        $this->assertSame('open_balance', PendingItem::query()->sole()->classification);
    }

    public function test_after_a_partial_payment_the_authorization_is_listed_as_open_balance_and_larger_payments_leave(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'MF MATERIAIS DE CONSTRUCAO', 14900);
        $paid = $this->payment($session, 'MF MATERIAIS DE CONSTRUCAO', 14370, ['paid_on' => '2026-07-10']);
        $larger = $this->payment($session, 'MF MATERIAIS DE CONSTRUCAO', 30000, ['paid_on' => '2026-07-11']);
        $smaller = $this->payment($session, 'MF MATERIAIS DE CONSTRUCAO', 200, ['paid_on' => '2026-07-12']);
        $this->reconcile($session);

        app(ConfirmSuggestion::class)->handle($this->operator(), $paid->suggestions()->sole(), $this->decision(DifferenceTreatment::StillOwed));

        $this->assertSame(530, $authorization->refresh()->balanceCents());
        $this->assertSame('superseded', $larger->suggestions()->sole()->status->value);
        $this->assertSame('pending', $smaller->suggestions()->sole()->status->value);
        $this->assertSame('partial', $smaller->suggestions()->sole()->classification->value);

        $items = PendingItem::query()->where('authorization_entry_id', $authorization->id)->pluck('classification', 'id');

        $this->assertSame(
            ['a-'.$authorization->id => 'open_balance', 's-'.$smaller->suggestions()->sole()->id => 'partial'],
            $items->sortKeys()->all(),
        );
        $this->assertSame('unmatched_payment', PendingItem::query()->findOrFail('p-'.$larger->id)->classification);
    }

    public function test_unlinking_the_partial_payment_brings_the_larger_candidates_back(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'MF MATERIAIS DE CONSTRUCAO', 14900);
        $paid = $this->payment($session, 'MF MATERIAIS DE CONSTRUCAO', 14370, ['paid_on' => '2026-07-10']);
        $larger = $this->payment($session, 'MF MATERIAIS DE CONSTRUCAO', 30000, ['paid_on' => '2026-07-11']);
        $this->reconcile($session);
        $link = app(ConfirmSuggestion::class)->handle($this->operator(), $paid->suggestions()->sole(), $this->decision(DifferenceTreatment::StillOwed));

        app(RemoveLink::class)->handle($this->operator(), $link);

        $this->assertNull($authorization->refresh()->state);
        $this->assertSame('pending', $larger->suggestions()->sole()->status->value);
        $this->assertSame('excess', $larger->suggestions()->sole()->classification->value);
        $this->assertFalse(PendingItem::query()->where('classification', 'open_balance')->exists());
    }

    public function test_a_second_payment_clears_the_balance(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $authorization = $this->authorization($session, 'CONSTRUTORA HORIZONTE', 300000);
        $first = $this->payment($session, 'CONSTRUTORA HORIZONTE', 100000);
        $second = $this->payment($session, 'CONSTRUTORA HORIZONTE', 200000);
        $this->reconcile($session);

        app(ConfirmSuggestion::class)->handle($this->operator(), $first->suggestions()->sole(), $this->decision(DifferenceTreatment::StillOwed));

        $remaining = $second->suggestions()->sole();

        $this->assertSame(0, $remaining->difference_cents);
        $this->assertSame('doubtful', $remaining->classification->value);

        app(ConfirmSuggestion::class)->handle($this->operator(), $remaining);

        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->refresh()->status());
        $this->assertSame(0, PendingItem::query()->count());
    }

    public function test_partial_closed_with_discount_reconciles_and_needs_a_justification(): void
    {
        [, $authorization, $suggestion] = $this->suggested(100000, 95000);
        $operator = $this->operator();

        foreach ([null, $this->decision(DifferenceTreatment::Discount), $this->decision(DifferenceTreatment::Overpayment)] as $invalid) {
            try {
                app(ConfirmSuggestion::class)->handle($operator, $suggestion, $invalid);
                $this->fail('The decision should have been refused.');
            } catch (ActionRefusedException) {
                $this->assertSame(0, ReconciliationLink::query()->count());
            }
        }

        $link = app(ConfirmSuggestion::class)->handle($operator, $suggestion, $this->decision(DifferenceTreatment::Discount, justified: true));

        $this->assertSame(5000, $link->discount_cents);
        $this->assertSame(JustificationCategory::CommercialDiscount, $link->justification_category);
        $this->assertSame('Negociado com o fornecedor', $link->justification);
        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->refresh()->status());
        $this->assertSame(0, $authorization->balanceCents());

        $log = AuditLog::query()->where('action', AuditAction::SuggestionConfirmed)->sole();

        $this->assertSame('discount', $log->after['treatment']);
        $this->assertSame('commercial_discount', $log->after['justification_category']);
        $this->assertSame('Negociado com o fornecedor', $log->after['justification']);
    }

    public function test_excess_confirmed_as_overpayment_settles_with_a_permanent_alert(): void
    {
        [$session, $authorization, $suggestion] = $this->suggested(50000, 65000);

        $link = app(ConfirmSuggestion::class)->handle($this->operator(), $suggestion, $this->decision(DifferenceTreatment::Overpayment));

        $this->assertSame(DifferenceTreatment::Overpayment, $link->treatment);
        $this->assertSame(15000, $link->excess_cents);
        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->refresh()->status());

        $totals = app(RunTotals::class)->for($session->currentRun);

        $this->assertSame(['count' => 1, 'cents' => 15000], $totals['treatments']['overpayment']);
        $this->assertSame(1, $totals['reconciled_manually']);
    }

    public function test_surcharge_is_accepted_up_to_the_cap_in_effect_at_the_decision(): void
    {
        [, , $atTheCap] = $this->suggested(100000, 110000);
        $operator = $this->operator();

        $link = app(ConfirmSuggestion::class)->handle($operator, $atTheCap, new DifferenceDecision(DifferenceTreatment::AcceptedSurcharge, JustificationCategory::InterestOrFine, 'Juros por atraso'));

        $this->assertSame(DifferenceTreatment::AcceptedSurcharge, $link->treatment);
        $this->assertSame(10000, $link->excess_cents);
        $this->assertSame(1000, $link->surcharge_cap_basis_points);

        ReconciliationSettings::current()->update(['surcharge_cap_basis_points' => 500]);

        $this->assertSame(DifferenceTreatment::AcceptedSurcharge, $link->refresh()->treatment);
        $this->assertSame(1000, $link->surcharge_cap_basis_points);
    }

    public function test_surcharge_above_the_cap_is_refused(): void
    {
        [, , $aboveTheCap] = $this->suggested(100000, 110001);

        try {
            app(ConfirmSuggestion::class)->handle($this->operator(), $aboveTheCap, new DifferenceDecision(DifferenceTreatment::AcceptedSurcharge, JustificationCategory::InterestOrFine, 'Juros'));
            $this->fail('The surcharge should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.reconciliation.errors.surcharge_above_cap', ['cap' => '10']), $exception->getMessage());
        }

        try {
            app(ConfirmSuggestion::class)->handle($this->operator(), $aboveTheCap, new DifferenceDecision(DifferenceTreatment::AcceptedSurcharge));
            $this->fail('A surcharge without justification should have been refused.');
        } catch (ActionRefusedException) {
        }

        $this->assertSame(0, ReconciliationLink::query()->count());

        app(ConfirmSuggestion::class)->handle($this->operator(), $aboveTheCap, $this->decision(DifferenceTreatment::Overpayment));

        $this->assertSame(1, ReconciliationLink::query()->count());
    }

    public function test_open_balance_is_closed_with_a_discount_on_the_latest_link(): void
    {
        [$session, $authorization, $suggestion] = $this->suggested(100000, 96000);
        $operator = $this->operator();
        $link = app(ConfirmSuggestion::class)->handle($operator, $suggestion, $this->decision(DifferenceTreatment::StillOwed));

        $closed = app(CloseAuthorizationWithDiscount::class)->handle($operator, $authorization, JustificationCategory::Other, 'Saldo não será cobrado');

        $this->assertTrue($closed->is($link));
        $this->assertSame(4000, $closed->discount_cents);
        $this->assertSame(DifferenceTreatment::Discount, $closed->treatment);
        $this->assertSame(JustificationCategory::Other, $closed->justification_category);
        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->refresh()->status());
        $this->assertSame(0, PendingItem::query()->count());
        $this->assertSame(['count' => 1, 'cents' => 4000], app(RunTotals::class)->for($session->currentRun)['treatments']['discount']);

        $log = AuditLog::query()->where('action', AuditAction::AuthorizationClosedWithDiscount)->sole();

        $this->assertSame(4000, $log->before['balance_cents']);
        $this->assertSame('Saldo não será cobrado', $log->after['justification']);
    }

    public function test_closing_with_discount_needs_a_linked_payment_and_a_justification(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $withoutPayment = $this->authorization($session, 'SERRALHERIA UNIAO', 77700);
        $this->reconcile($session);

        foreach ([[$withoutPayment, 'Motivo'], [$withoutPayment, '  ']] as [$authorization, $justification]) {
            try {
                app(CloseAuthorizationWithDiscount::class)->handle($this->operator(), $authorization, JustificationCategory::Other, $justification);
                $this->fail('The discount should have been refused.');
            } catch (ActionRefusedException) {
                $this->assertSame(0, ReconciliationLink::query()->count());
            }
        }
    }

    public function test_undoing_a_confirmed_excess_brings_the_same_pair_back_to_be_decided_again(): void
    {
        [, $authorization, $suggestion] = $this->suggested(6490, 6900);
        $operator = $this->operator();
        $link = app(ConfirmSuggestion::class)->handle($operator, $suggestion, $this->decision(DifferenceTreatment::Overpayment));

        app(RemoveLink::class)->handle($operator, $link);

        $suggestion->refresh();

        $this->assertSame('pending', $suggestion->status->value);
        $this->assertSame('excess', $suggestion->classification->value);
        $this->assertSame(410, $suggestion->difference_cents);
        $this->assertSame(['s-'.$suggestion->id], PendingItem::query()->pluck('id')->all());
        $this->assertSame(0, ReconciliationPairBlock::query()->count());

        $again = app(ConfirmSuggestion::class)->handle($operator, $suggestion, new DifferenceDecision(DifferenceTreatment::AcceptedSurcharge, JustificationCategory::Freight, 'Frete'));

        $this->assertSame(DifferenceTreatment::AcceptedSurcharge, $again->treatment);
        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->refresh()->status());
    }

    public function test_unlinking_undoes_the_discount_of_that_link(): void
    {
        [, $authorization, $suggestion] = $this->suggested(100000, 95000);
        $operator = $this->operator();
        $link = app(ConfirmSuggestion::class)->handle($operator, $suggestion, $this->decision(DifferenceTreatment::Discount, justified: true));

        app(RemoveLink::class)->handle($operator, $link);

        $this->assertNull($authorization->refresh()->state);
        $this->assertSame(100000, $authorization->balanceCents());
        $this->assertSame(0, ReconciliationLink::query()->count());
    }

    public function test_every_discount_and_surcharge_has_category_justification_and_audit(): void
    {
        [, , $suggestion] = $this->suggested(100000, 95000);

        app(ConfirmSuggestion::class)->handle($this->operator(), $suggestion, $this->decision(DifferenceTreatment::Discount, justified: true));

        $treated = ReconciliationLink::query()->whereIn('treatment', [DifferenceTreatment::Discount, DifferenceTreatment::AcceptedSurcharge])->get();

        $this->assertCount(1, $treated);

        foreach ($treated as $link) {
            $this->assertNotNull($link->justification_category);
            $this->assertNotEmpty($link->justification);
            $this->assertTrue(AuditLog::query()->where('auditable_type', 'reconciliation_link')->where('auditable_id', $link->id)->exists());
        }
    }
}
