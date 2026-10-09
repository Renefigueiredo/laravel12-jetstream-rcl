<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\CreateMatchingAuthorization;
use App\Actions\Conciliation\RemoveLink;
use App\Enums\AuditAction;
use App\Enums\AuthorizationStatus;
use App\Enums\ImportSlot;
use App\Enums\LinkOrigin;
use App\Livewire\Reconciliation\CardEntriesTable;
use App\Livewire\Reconciliation\InvestigationTable;
use App\Livewire\Reconciliation\LinksTable;
use App\Livewire\Reconciliation\PendingTable;
use App\Livewire\Reconciliation\Show;
use App\Models\AuditLog;
use App\Models\AuthorizationEntry;
use App\Models\ExcludedOperationCode;
use App\Models\PendingItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsReconciliations;
use Tests\TestCase;

class InvestigationQueueTest extends TestCase
{
    use BuildsReconciliations;
    use RefreshDatabase;

    public function test_queue_lists_only_payments_without_authorization(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '11024465']);
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $linked = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $this->authorization($session, 'GRAFICA SUL', 30000);
        $suggested = $this->payment($session, 'GRAFICA SUL', 10000);
        $excluded = $this->payment($session, 'BANCO EMISSOR', 1350, ['operation_code' => '11024465']);
        $orphan = $this->payment($session, 'POSTO ALFA', 7000, ['card' => '0798', 'species' => 'FATURA CARTAO 0798', 'operation_name' => 'COMBUSTIVEL']);
        $social = $this->payment($session, 'RESTAURANTE SABOR', 4500, [], ImportSlot::PaymentsSocial);
        $this->reconcile($session);

        Livewire::actingAs($this->operator())
            ->test(InvestigationTable::class, ['sessionId' => $session->id])
            ->assertCountTableRecords(2)
            ->assertCanSeeTableRecords([$orphan, $social])
            ->assertCanNotSeeTableRecords([$linked, $suggested, $excluded])
            ->assertSee(['POSTO ALFA', 'COMBUSTIVEL', 'FATURA CARTAO 0798', 'R$ 70,00'])
            ->filterTable('unit', 'social')
            ->assertCanSeeTableRecords([$social])
            ->assertCanNotSeeTableRecords([$orphan])
            ->resetTableFilters()
            ->filterTable('card', '0798')
            ->assertCanSeeTableRecords([$orphan])
            ->assertCanNotSeeTableRecords([$social])
            ->resetTableFilters()
            ->searchTable('RESTAURANTE')
            ->assertCanSeeTableRecords([$social])
            ->assertCanNotSeeTableRecords([$orphan]);

        Livewire::actingAs($this->operator())
            ->withQueryParams(['aba' => 'investigacao'])
            ->test(Show::class, ['session' => $session])
            ->assertSet('tab', 'investigacao')
            ->assertSeeLivewire(InvestigationTable::class);
    }

    public function test_administrator_creates_the_matching_authorization(): void
    {
        $this->travelTo('2026-08-10 09:15:00');
        $session = $this->sessionWithFiles(state: 'open');
        $payment = $this->payment($session, 'POSTO ALFA', 7000, ['card' => '0798']);
        $this->reconcile($session);
        $administrator = User::factory()->administrador()->create();

        Livewire::actingAs($administrator)
            ->test(InvestigationTable::class, ['sessionId' => $session->id])
            ->assertTableActionVisible('createAuthorization', $payment)
            ->mountTableAction('createAuthorization', $payment)
            ->assertMountedActionModalSee(['POSTO ALFA', 'R$ 70,00'])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['justification' => 'required'])
            ->setTableActionData(['justification' => 'Abastecimento de emergência da ambulância.'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertDispatched('reconciliation-changed')
            ->assertDispatched('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.authorization_created'))
            ->assertCountTableRecords(0);

        $authorization = AuthorizationEntry::query()->sole();
        $link = $payment->refresh()->link;

        $this->assertTrue($authorization->isCreatedInReconciliation());
        $this->assertSame('POSTO ALFA', $authorization->supplier_name);
        $this->assertSame(7000, $authorization->amount_cents);
        $this->assertTrue($authorization->authorized_on->isSameDay($payment->paid_on));
        $this->assertSame('0798', $authorization->card);
        $this->assertSame('Abastecimento de emergência da ambulância.', $authorization->request);
        $this->assertSame($administrator->id, $authorization->created_by);
        $this->assertSame($payment->id, $authorization->source_payment_entry_id);
        $this->assertSame($session->id, $authorization->reconciliation_session_id);
        $this->assertSame(AuthorizationStatus::Reconciled, $authorization->status());
        $this->assertSame($authorization->id, $link->authorization_entry_id);
        $this->assertSame(LinkOrigin::Manual, $link->origin);
        $this->assertSame($administrator->id, $link->decided_by);
        $this->assertSame(0, PendingItem::query()->count());

        $log = AuditLog::query()->where('action', AuditAction::AuthorizationCreatedInReconciliation)->sole();

        $this->assertTrue($log->user->is($administrator));
        $this->assertSame($authorization->id, $log->auditable_id);
        $this->assertSame($payment->id, $log->after['payment_entry_id']);
        $this->assertSame(7000, $log->after['amount_cents']);
        $this->assertSame('Abastecimento de emergência da ambulância.', $log->after['justification']);
        $this->assertSame('2026-08-10 09:15:00', $log->created_at->format('Y-m-d H:i:s'));

        Livewire::actingAs($this->operator())
            ->test(LinksTable::class, ['sessionId' => $session->id, 'card' => '0798'])
            ->assertCountTableRecords(1)
            ->dispatch('reconciliation-changed')
            ->assertCanSeeTableRecords([$link])
            ->assertSee(__('conciliation.reconciliation.details.created_in_reconciliation'))
            ->assertSee(__('conciliation.reconciliation.link_origin.created'))
            ->filterTable('origin', 'created')
            ->assertCountTableRecords(1)
            ->filterTable('origin', 'automatic')
            ->assertCountTableRecords(0);
    }

    public function test_only_an_administrator_may_create_the_authorization(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $payment = $this->payment($session, 'POSTO ALFA', 7000, ['card' => '0798', 'species' => 'FATURA CARTAO 0798']);
        $this->reconcile($session);
        $operator = $this->operator();
        $item = PendingItem::query()->findOrFail('p-'.$payment->id);

        Livewire::actingAs($operator)
            ->test(InvestigationTable::class, ['sessionId' => $session->id])
            ->assertTableActionHidden('createAuthorization', $payment)
            ->assertTableActionVisible('linkToAuthorization', $payment);

        Livewire::actingAs($operator)
            ->test(CardEntriesTable::class, ['sessionId' => $session->id, 'card' => '0798', 'kind' => 'payments'])
            ->assertTableActionHidden('createAuthorization', $payment);

        Livewire::actingAs($operator)
            ->test(PendingTable::class, ['sessionId' => $session->id])
            ->call('filterBy', 'unmatched_payment')
            ->assertTableActionHidden('createAuthorization', $item);

        Livewire::actingAs(User::factory()->administrador()->create())
            ->test(CardEntriesTable::class, ['sessionId' => $session->id, 'card' => '0798', 'kind' => 'payments'])
            ->assertTableActionVisible('createAuthorization', $payment);

        try {
            app(CreateMatchingAuthorization::class)->handle($operator, $payment, 'Sem autorização.');
            $this->fail('An operator created an authorization.');
        } catch (AuthorizationException) {
            $this->assertSame(0, AuthorizationEntry::query()->count());
            $this->assertNull($payment->refresh()->link);
        }
    }

    public function test_unlinking_undoes_the_created_authorization(): void
    {
        $session = $this->sessionWithFiles(state: 'open');
        $payment = $this->payment($session, 'POSTO ALFA', 7000);
        $this->reconcile($session);
        $administrator = User::factory()->administrador()->create();

        $link = app(CreateMatchingAuthorization::class)->handle($administrator, $payment, 'Compra de emergência.');

        app(RemoveLink::class)->handle($this->operator(), $link);

        $this->assertSame(0, AuthorizationEntry::query()->count());
        $this->assertSame(['p-'.$payment->id], PendingItem::query()->pluck('id')->all());
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::LinkRemoved)->sole()->after['authorization_deleted']);

        $again = app(CreateMatchingAuthorization::class)->handle($administrator, $payment, 'Compra de emergência, revista.');

        $this->assertSame($payment->id, $again->payment_entry_id);
    }

    public function test_creation_is_refused_when_the_payment_cannot_be_linked(): void
    {
        ExcludedOperationCode::factory()->create(['code' => '11024465']);
        $session = $this->sessionWithFiles(state: 'open');
        $this->authorization($session, 'PADARIA PERNAMBUCANA', 10000);
        $linked = $this->payment($session, 'PADARIA PERNAMBUCANA', 10000);
        $excluded = $this->payment($session, 'BANCO EMISSOR', 1350, ['operation_code' => '11024465']);
        $orphan = $this->payment($session, 'POSTO ALFA', 7000);
        $this->reconcile($session);
        $administrator = User::factory()->administrador()->create();

        foreach ([
            [$linked, 'Sem autorização.', 'payment_already_linked'],
            [$excluded, 'Sem autorização.', 'payment_skipped'],
            [$orphan, '   ', 'creation_justification_required'],
            [$orphan, str_repeat('a', 501), 'justification_too_long'],
        ] as [$payment, $justification, $error]) {
            try {
                app(CreateMatchingAuthorization::class)->handle($administrator, $payment, $justification);
                $this->fail('The creation was accepted: '.$error);
            } catch (ActionRefusedException $exception) {
                $this->assertSame(__('conciliation.reconciliation.errors.'.$error), $exception->getMessage());
            }
        }

        $this->assertSame(1, AuthorizationEntry::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::AuthorizationCreatedInReconciliation)->count());
    }
}
