<?php

namespace App\Livewire\Reconciliation;

use App\Livewire\Reconciliation\Concerns\DecidesPendingItems;
use App\Livewire\Reconciliation\Concerns\ShowsEntryDetails;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\ReconciliationSession;
use App\Services\Reconciliation\CardStatement;
use App\Support\Money;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CardEntriesTable extends Component implements HasActions, HasSchemas, HasTable
{
    use DecidesPendingItems;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ShowsEntryDetails;

    public const KINDS = ['authorizations', 'payments'];

    #[Locked]
    public int $sessionId;

    #[Locked]
    public string $card;

    #[Locked]
    public string $kind;

    public function mount(int $sessionId, string $card, string $kind): void
    {
        $this->authorize('view', ReconciliationSession::query()->findOrFail($sessionId));

        abort_unless(in_array($kind, self::KINDS, true), 404);

        $this->sessionId = $sessionId;
        $this->card = $card;
        $this->kind = $kind;
    }

    public function table(Table $table): Table
    {
        return $this->kind === 'authorizations'
            ? $this->authorizationsTable($table)
            : $this->paymentsTable($table);
    }

    #[On('reconciliation-changed')]
    public function refreshEntries(): void
    {
        $this->resetTable();
    }

    public function render(): View
    {
        return view('livewire.reconciliation.table');
    }

    protected function authorizationsTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(CardStatement::class)->authorizationsWithoutInvoice($this->sessionId, $this->card))
            ->columns([
                TextColumn::make('supplier_name')
                    ->label(__('conciliation.reconciliation.columns.authorization'))
                    ->action($this->authorizationDetailsAction('openAuthorization'))
                    ->description(fn (AuthorizationEntry $record): string => implode(' · ', array_filter([
                        Str::limit($record->request, 70),
                        $record->authorized_on->format('d/m/Y'),
                        $record->payment_method,
                        $record->payment_condition,
                    ])))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('amount_cents')
                    ->label(__('conciliation.reconciliation.columns.authorized'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->alignEnd(),
            ])
            ->defaultSort('authorized_on')
            ->recordActions([$this->linkAction(), $this->authorizationDetailsAction()])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading(__('conciliation.reconciliation.cards.none'));
    }

    protected function paymentsTable(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(CardStatement::class)->linesWithoutAuthorization($this->sessionId, $this->card))
            ->columns([
                TextColumn::make('supplier_name')
                    ->label(__('conciliation.reconciliation.columns.payment'))
                    ->action($this->paymentDetailsAction('openPayment'))
                    ->description(fn (PaymentEntry $record): string => implode(' · ', array_filter([
                        $record->unit->label(),
                        $record->paid_on->format('d/m/Y'),
                        $record->species,
                        trim($record->operation_code.' '.$record->operation_name),
                    ])))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('amount_cents')
                    ->label(__('conciliation.reconciliation.columns.paid'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->alignEnd(),
            ])
            ->defaultSort('paid_on')
            ->recordActions([$this->linkToAuthorizationAction(), $this->createAuthorizationAction(), $this->paymentDetailsAction()])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading(__('conciliation.reconciliation.cards.none'));
    }
}
