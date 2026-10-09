<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Enums\AuthorizationStatus;
use App\Enums\DifferenceTreatment;
use App\Enums\DifferenceType;
use App\Enums\JustificationCategory;
use App\Models\AuthorizationState;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class AuthorizationStateTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_authorization_without_links_is_open_with_the_full_balance(): void
    {
        $authorization = $this->authorization($this->sessionWithFiles(), 'PADARIA PERNAMBUCANA', 300000);

        $this->assertNull($authorization->state);
        $this->assertSame(AuthorizationStatus::Open, $authorization->status());
        $this->assertSame(300000, $authorization->balanceCents());
    }

    public function test_payment_below_the_balance_leaves_it_partial(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 300000);

        $link = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 100000), attributes: ['treatment' => DifferenceTreatment::StillOwed]);

        $state = $authorization->refresh()->state;

        $this->assertSame(DifferenceType::Partial, $link->difference_type);
        $this->assertSame(DifferenceTreatment::StillOwed, $link->treatment);
        $this->assertSame(AuthorizationStatus::Partial, $state->status);
        $this->assertSame(1, $state->links_count);
        $this->assertSame(100000, $state->paid_cents);
        $this->assertSame(200000, $state->balance_cents);
    }

    public function test_payment_that_clears_the_balance_reconciles_it(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 300000);

        $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 100000));
        $last = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 200000));

        $state = $authorization->refresh()->state;

        $this->assertSame(DifferenceType::Exact, $last->difference_type);
        $this->assertSame(AuthorizationStatus::Reconciled, $state->status);
        $this->assertSame(0, $state->balance_cents);
        $this->assertSame(2, $state->links_count);
    }

    public function test_remainder_within_the_tolerance_is_written_off(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 43000);

        $link = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 42970));

        $state = $authorization->refresh()->state;

        $this->assertSame(DifferenceType::Exact, $link->difference_type);
        $this->assertSame(30, $link->tolerance_writeoff_cents);
        $this->assertSame(JustificationCategory::WithinTolerance, $link->justification_category);
        $this->assertSame(-30, $link->difference_cents);
        $this->assertSame(30, $state->writeoff_cents);
        $this->assertSame(0, $state->balance_cents);
        $this->assertSame(AuthorizationStatus::Reconciled, $state->status);
    }

    public function test_payment_slightly_above_within_the_tolerance_is_exact(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 43000);

        $link = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 43050));

        $this->assertSame(DifferenceType::Exact, $link->difference_type);
        $this->assertSame(0, $link->excess_cents);
        $this->assertSame(0, $link->tolerance_writeoff_cents);
        $this->assertSame(0, $authorization->refresh()->state->balance_cents);
    }

    public function test_tolerance_boundary_one_cent_beyond_is_not_exact(): void
    {
        $session = $this->sessionWithFiles();
        $below = $this->authorization($session, 'A', 43000);
        $above = $this->authorization($session, 'B', 43000);

        $partial = $this->linkPair($below, $this->payment($session, 'A', 42949), attributes: ['treatment' => DifferenceTreatment::StillOwed]);
        $excess = $this->linkPair($above, $this->payment($session, 'B', 43051), attributes: ['treatment' => DifferenceTreatment::Overpayment]);

        $this->assertSame(DifferenceType::Partial, $partial->difference_type);
        $this->assertSame(51, $below->refresh()->state->balance_cents);
        $this->assertSame(DifferenceType::Excess, $excess->difference_type);
        $this->assertSame(51, $excess->excess_cents);
        $this->assertSame(AuthorizationStatus::Reconciled, $above->refresh()->state->status);
    }

    public function test_payment_above_the_balance_records_the_excess(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 50000);

        $link = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 65000), attributes: ['treatment' => DifferenceTreatment::Overpayment]);

        $this->assertSame(DifferenceType::Excess, $link->difference_type);
        $this->assertSame(15000, $link->excess_cents);
        $this->assertSame(0, $authorization->refresh()->state->balance_cents);
        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->state->status);
    }

    public function test_discount_is_always_the_remaining_balance(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 100000);

        $link = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 95000), attributes: [
            'treatment' => DifferenceTreatment::Discount,
            'justificationCategory' => JustificationCategory::CommercialDiscount,
            'justification' => 'Desconto negociado',
        ]);

        $state = $authorization->refresh()->state;

        $this->assertSame(5000, $link->discount_cents);
        $this->assertSame(5000, $state->discount_cents);
        $this->assertSame(0, $state->balance_cents);
        $this->assertSame(AuthorizationStatus::Reconciled, $state->status);
    }

    public function test_unlinking_recalculates_and_removes_the_state_when_nothing_is_left(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 300000);
        $first = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 100000));
        $second = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 200000));

        $this->unlinkPair($second);

        $this->assertSame(200000, $authorization->refresh()->state->balance_cents);
        $this->assertSame(AuthorizationStatus::Partial, $authorization->state->status);

        $this->unlinkPair($first);

        $this->assertSame(0, AuthorizationState::query()->count());
        $this->assertSame(AuthorizationStatus::Open, $authorization->refresh()->status());
        $this->assertSame(300000, $authorization->balanceCents());
    }

    public function test_unlinking_clears_the_rounding_of_the_remaining_links(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 100000);
        $first = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 50000));
        $closing = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 49980));

        $this->assertSame(20, $closing->tolerance_writeoff_cents);

        $this->unlinkPair($first);

        $this->assertSame(0, $closing->refresh()->tolerance_writeoff_cents);
        $this->assertSame(50020, $authorization->refresh()->state->balance_cents);
        $this->assertSame(AuthorizationStatus::Partial, $authorization->state->status);
    }

    public function test_a_payment_cannot_be_linked_twice(): void
    {
        $session = $this->sessionWithFiles();
        $first = $this->authorization($session, 'A', 10000);
        $second = $this->authorization($session, 'B', 10000);
        $payment = $this->payment($session, 'A', 10000);

        $this->linkPair($first, $payment);

        try {
            $this->linkPair($second, $payment);
            $this->fail('The second link of the same payment should have been refused.');
        } catch (ActionRefusedException $exception) {
            $this->assertSame(__('conciliation.reconciliation.errors.payment_already_linked'), $exception->getMessage());
        }

        $this->assertSame(1, ReconciliationLink::query()->count());
        $this->assertNull($second->refresh()->state);
    }

    public function test_the_second_action_on_an_authorization_sees_the_updated_balance(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 100000);
        $stale = $authorization->fresh();

        $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 60000));
        $second = $this->linkPair($stale, $this->payment($session, 'PADARIA PERNAMBUCANA', 60000), attributes: ['treatment' => DifferenceTreatment::Overpayment]);

        $this->assertSame(DifferenceType::Excess, $second->difference_type);
        $this->assertSame(20000, $second->excess_cents);
        $this->assertSame(0, $authorization->refresh()->state->balance_cents);
        $this->assertSame(120000, $authorization->state->paid_cents);
    }

    public function test_decisions_use_the_tolerance_of_the_run_not_the_current_one(): void
    {
        $session = $this->sessionWithFiles();
        $run = $this->runFor($session, ['tolerance_cents' => 50]);
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 43000);

        ReconciliationSettings::current()->update(['tolerance_cents' => 500]);

        $link = $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', 42900), $run, ['treatment' => DifferenceTreatment::StillOwed]);

        $this->assertSame(DifferenceType::Partial, $link->difference_type);
        $this->assertSame(100, $authorization->refresh()->state->balance_cents);
    }

    public function test_balance_always_equals_authorized_minus_payments_discounts_and_rounding(): void
    {
        $session = $this->sessionWithFiles();
        $authorization = $this->authorization($session, 'PADARIA PERNAMBUCANA', 100000);

        foreach ([30000, 30000, 39970] as $amount) {
            $this->linkPair($authorization, $this->payment($session, 'PADARIA PERNAMBUCANA', $amount));

            $state = $authorization->refresh()->state;

            $this->assertSame(
                max(0, 100000 - $state->paid_cents - $state->discount_cents - $state->writeoff_cents),
                $state->balance_cents,
            );
        }

        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->state->status);
    }
}
