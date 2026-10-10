<?php

namespace App\Livewire\Dashboard;

use App\Enums\OperatingUnit;
use App\Enums\SessionStatus;
use App\Livewire\Concerns\FiltersByPeriodAndAmount;
use App\Livewire\Reconciliation\Concerns\DecidesPendingItems;
use App\Livewire\Reconciliation\Concerns\ShowsEntryDetails;
use App\Models\PaymentEntry;
use App\Models\PendingItem;
use App\Models\ReconciliationSession;
use App\Services\Reconciliation\AuthorizationPanel;
use App\Support\Money;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class DivergencesTable extends Component implements HasActions, HasSchemas, HasTable
{
    use DecidesPendingItems;
    use FiltersByPeriodAndAmount;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ShowsEntryDetails;

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

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(AuthorizationPanel::class)->divergences()
                ->with(['payment.session', 'authorization.state', 'authorization.session', 'suggestion.run'])
                ->orderBy('reconciliation_session_id')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('classification')
                    ->label(__('conciliation.dashboard.divergences.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('conciliation.dashboard.divergences.types.'.$state))
                    ->color(fn (string $state): string => $state === 'excess' ? 'danger' : 'gray')
                    ->width('8rem'),
                TextColumn::make('payment_supplier')
                    ->label(__('conciliation.reconciliation.columns.payment'))
                    ->action($this->paymentDetailsAction('openPayment'))
                    ->description(fn (PendingItem $record): string => implode(' · ', array_filter([
                        $record->payment->unit->label(),
                        $record->payment->paid_on->format('d/m/Y'),
                        trim($record->payment->operation_code.' '.$record->payment->operation_name),
                        $record->payment->session->label(),
                    ])))
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->searchPayment($query, $search, 'payment')),
                TextColumn::make('payment.amount_cents')
                    ->label(__('conciliation.reconciliation.columns.paid'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->alignEnd(),
                TextColumn::make('authorization_supplier')
                    ->label(__('conciliation.dashboard.divergences.suggested'))
                    ->placeholder(__('conciliation.reconciliation.pending.none'))
                    ->action($this->authorizationDetailsAction('openAuthorization'))
                    ->description(fn (PendingItem $record): ?string => $record->authorization === null ? null : implode(' · ', array_filter([
                        __('conciliation.reconciliation.columns.balance_of', ['amount' => Money::format($record->authorization->balanceCents())]),
                        $record->authorization->session->label(),
                    ])))
                    ->wrap(),
                TextColumn::make('difference_cents')
                    ->label(__('conciliation.dashboard.divergences.over_the_balance'))
                    ->placeholder('')
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->alignEnd(),
            ])
            ->filters([
                ...$this->paymentFilters('payment'),
                SelectFilter::make('classification')
                    ->label(__('conciliation.dashboard.divergences.type'))
                    ->options([
                        'unmatched_payment' => __('conciliation.dashboard.divergences.types.unmatched_payment'),
                        'excess' => __('conciliation.dashboard.divergences.types.excess'),
                    ]),
                SelectFilter::make('unit')
                    ->label(__('conciliation.reconciliation.columns.unit'))
                    ->options(collect(OperatingUnit::cases())->mapWithKeys(
                        fn (OperatingUnit $unit): array => [$unit->value => $unit->label()],
                    )->all())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('payment', fn (Builder $query) => $query->where('unit', $data['value']))),
                SelectFilter::make('reconciliation_session_id')
                    ->label(__('conciliation.dashboard.divergences.session'))
                    ->options(fn (): array => ReconciliationSession::query()
                        ->where('status', SessionStatus::Processed)
                        ->orderByDesc('period')
                        ->get()
                        ->mapWithKeys(fn (ReconciliationSession $session): array => [$session->id => $session->label()])
                        ->all()),
                SelectFilter::make('operation_code')
                    ->label(__('conciliation.reconciliation.columns.operation'))
                    ->options(fn (): array => $this->paymentValues('operation_code'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('payment', fn (Builder $query) => $query->where('operation_code', $data['value']))),
                SelectFilter::make('payment_card')
                    ->label(__('conciliation.reconciliation.columns.card'))
                    ->options(fn (): array => $this->paymentValues('card')),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->searchPlaceholder(__('conciliation.filters.search.payments'))
            ->recordActions([
                $this->linkToAuthorizationAction(),
                $this->confirmAction(),
                $this->rejectAction(),
                $this->createAuthorizationAction(),
                $this->authorizationDetailsAction(),
                $this->paymentDetailsAction(),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('conciliation.dashboard.divergences.empty'));
    }

    #[On('reconciliation-changed')]
    public function refreshList(): void
    {
        $this->resetTable();
    }

    public function render(): View
    {
        return view('livewire.reconciliation.table');
    }

    /**
     * @return array<string, string>
     */
    protected function paymentValues(string $column): array
    {
        $values = PaymentEntry::query()
            ->whereIn('id', app(AuthorizationPanel::class)->divergences()->select('payment_entry_id'))
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        return $values->combine($values)->all();
    }
}
