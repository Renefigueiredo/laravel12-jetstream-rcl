<?php

namespace App\Livewire\Reconciliation;

use App\Enums\OperatingUnit;
use App\Enums\PendingItemKind;
use App\Livewire\Reconciliation\Concerns\DecidesPendingItems;
use App\Livewire\Reconciliation\Concerns\ShowsEntryDetails;
use App\Models\PaymentEntry;
use App\Models\PendingItem;
use App\Models\ReconciliationSession;
use App\Support\Money;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class InvestigationTable extends Component implements HasActions, HasSchemas, HasTable
{
    use DecidesPendingItems;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ShowsEntryDetails;

    #[Locked]
    public int $sessionId;

    /**
     * @var string|null
     */
    #[Url(as: 'busca')]
    public $tableSearch = '';

    /**
     * @var array<string, mixed>|null
     */
    #[Url(as: 'filtros')]
    public ?array $tableFilters = null;

    public function mount(int $sessionId): void
    {
        $this->authorize('view', ReconciliationSession::query()->findOrFail($sessionId));

        $this->sessionId = $sessionId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->payments())
            ->columns([
                TextColumn::make('unit')
                    ->label(__('conciliation.reconciliation.columns.unit'))
                    ->formatStateUsing(fn (OperatingUnit $state): string => $state->label()),
                TextColumn::make('supplier_name')
                    ->label(__('conciliation.reconciliation.columns.payment'))
                    ->action($this->paymentDetailsAction('openPayment'))
                    ->description(fn (PaymentEntry $record): string => implode(' · ', array_filter([
                        $record->paid_on->format('d/m/Y'),
                        $record->species,
                        $record->transaction_type,
                    ])))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('operation_code')
                    ->label(__('conciliation.reconciliation.columns.operation'))
                    ->description(fn (PaymentEntry $record): ?string => $record->operation_name)
                    ->wrap(),
                TextColumn::make('amount_cents')
                    ->label(__('conciliation.reconciliation.columns.paid'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('unit')
                    ->label(__('conciliation.reconciliation.columns.unit'))
                    ->options(collect(OperatingUnit::cases())->mapWithKeys(
                        fn (OperatingUnit $unit): array => [$unit->value => $unit->label()],
                    )->all()),
                SelectFilter::make('operation_code')
                    ->label(__('conciliation.reconciliation.columns.operation'))
                    ->options(fn (): array => $this->distinct('operation_code'))
                    ->searchable(),
                SelectFilter::make('species')
                    ->label(__('conciliation.reconciliation.columns.species'))
                    ->options(fn (): array => $this->distinct('species')),
                SelectFilter::make('card')
                    ->label(__('conciliation.reconciliation.columns.card'))
                    ->options(fn (): array => $this->distinct('card')),
            ])
            ->defaultSort('paid_on')
            ->recordActions([
                $this->linkToAuthorizationAction(),
                $this->createAuthorizationAction(),
                $this->paymentDetailsAction(),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('conciliation.reconciliation.investigation.empty'));
    }

    public function render(): View
    {
        return view('livewire.reconciliation.table');
    }

    /**
     * Payments of the session that entered the comparison and have neither link nor suggestion.
     *
     * @return Builder<PaymentEntry>
     */
    protected function payments(): Builder
    {
        return PaymentEntry::query()->whereIn('id', PendingItem::query()
            ->where('reconciliation_session_id', $this->sessionId)
            ->where('kind', PendingItemKind::UnmatchedPayment)
            ->select('payment_entry_id'));
    }

    /**
     * @return array<string, string>
     */
    protected function distinct(string $column): array
    {
        $values = $this->payments()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column);

        return $values->combine($values)->all();
    }
}
