<?php

namespace App\Livewire\Reconciliation;

use App\Models\ReconciliationSession;
use App\Services\Reconciliation\CardStatement;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class CardsTable extends Component
{
    #[Locked]
    public int $sessionId;

    #[Url(as: 'cartao')]
    public ?string $card = null;

    public function mount(int $sessionId): void
    {
        $this->authorize('view', ReconciliationSession::query()->findOrFail($sessionId));

        $this->sessionId = $sessionId;
    }

    public function choose(?string $card): void
    {
        $this->card = $card;
    }

    #[On('reconciliation-changed')]
    public function refreshSummary(): void
    {
        unset($this->summary);
    }

    /**
     * One row per card of the session, every figure counted or summed by the database.
     *
     * @return list<array<string, int|string>>
     */
    #[Computed]
    public function summary(): array
    {
        $authorizations = app(CardStatement::class)->authorizations($this->sessionId)
            ->toBase()
            ->selectRaw('authorization_entries.card as card, COUNT(*) as items, COALESCE(SUM(authorization_entries.amount_cents), 0) as cents')
            ->selectRaw('SUM(CASE WHEN authorization_states.authorization_entry_id IS NULL THEN 1 ELSE 0 END) as unpaid')
            ->selectRaw('COALESCE(SUM(CASE WHEN authorization_states.authorization_entry_id IS NULL THEN authorization_entries.amount_cents ELSE 0 END), 0) as unpaid_cents')
            ->groupBy('authorization_entries.card')
            ->get()
            ->keyBy('card');

        $payments = app(CardStatement::class)->invoiceLines($this->sessionId)
            ->toBase()
            ->selectRaw('payment_entries.card as card')
            ->selectRaw('SUM(CASE WHEN reconciliation_skips.id IS NULL THEN 1 ELSE 0 END) as items')
            ->selectRaw('COALESCE(SUM(CASE WHEN reconciliation_skips.id IS NULL THEN payment_entries.amount_cents ELSE 0 END), 0) as cents')
            ->selectRaw('SUM(CASE WHEN reconciliation_skips.id IS NOT NULL THEN 1 ELSE 0 END) as excluded')
            ->selectRaw('SUM(CASE WHEN reconciliation_links.id IS NOT NULL THEN 1 ELSE 0 END) as linked')
            ->selectRaw('SUM(CASE WHEN reconciliation_skips.id IS NULL AND reconciliation_links.id IS NULL THEN 1 ELSE 0 END) as unlinked')
            ->selectRaw('COALESCE(SUM(CASE WHEN reconciliation_skips.id IS NULL AND reconciliation_links.id IS NULL THEN payment_entries.amount_cents ELSE 0 END), 0) as unlinked_cents')
            ->groupBy('payment_entries.card')
            ->get()
            ->keyBy('card');

        $cards = $authorizations->keys()->merge($payments->keys())->unique()->sort()->values();

        return $cards->map(fn (string $card): array => [
            'card' => $card,
            'authorizations' => (int) ($authorizations[$card]->items ?? 0),
            'authorized_cents' => (int) ($authorizations[$card]->cents ?? 0),
            'invoice_lines' => (int) ($payments[$card]->items ?? 0),
            'invoice_cents' => (int) ($payments[$card]->cents ?? 0),
            'linked' => (int) ($payments[$card]->linked ?? 0),
            'authorizations_without_invoice' => (int) ($authorizations[$card]->unpaid ?? 0),
            'authorizations_without_invoice_cents' => (int) ($authorizations[$card]->unpaid_cents ?? 0),
            'lines_without_authorization' => (int) ($payments[$card]->unlinked ?? 0),
            'lines_without_authorization_cents' => (int) ($payments[$card]->unlinked_cents ?? 0),
            'excluded' => (int) ($payments[$card]->excluded ?? 0),
        ])->all();
    }

    public function render(): View
    {
        return view('livewire.reconciliation.cards-table');
    }
}
