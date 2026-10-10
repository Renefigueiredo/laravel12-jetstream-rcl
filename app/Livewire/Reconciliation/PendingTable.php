<?php

namespace App\Livewire\Reconciliation;

use App\Enums\PendingItemKind;
use App\Livewire\Concerns\FiltersByPeriodAndAmount;
use App\Livewire\Reconciliation\Concerns\DecidesPendingItems;
use App\Livewire\Reconciliation\Concerns\ShowsEntryDetails;
use App\Models\PaymentEntry;
use App\Models\PendingItem;
use App\Models\ReconciliationSession;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\InstallmentForecaster;
use App\Services\Reconciliation\Matching\InstallmentSchedule;
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
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class PendingTable extends Component implements HasActions, HasSchemas, HasTable
{
    use DecidesPendingItems;
    use FiltersByPeriodAndAmount;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ShowsEntryDetails;

    /**
     * Quick filters, in the order shown; "todos" leaves out the payments without authorization.
     *
     * @var list<string>
     */
    public const CLASSIFICATIONS = ['todos', 'doubtful', 'partial', 'excess', 'unmatched_authorization', 'open_balance', 'unmatched_payment'];

    #[Locked]
    public int $sessionId;

    #[Url(as: 'filtro')]
    public string $classification = 'todos';

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

        if (! in_array($this->classification, self::CLASSIFICATIONS, true)) {
            $this->classification = 'todos';
        }
    }

    public function filterBy(string $classification): void
    {
        if (in_array($classification, self::CLASSIFICATIONS, true)) {
            $this->classification = $classification;
            $this->resetPage();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->items())
            ->columns([
                TextColumn::make('classification')
                    ->label(__('conciliation.reconciliation.columns.classification'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('conciliation.reconciliation.pending.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'doubtful' => 'warning',
                        'partial', 'open_balance' => 'info',
                        'excess' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (PendingItem $record): ?string => $this->warnings($record))
                    ->tooltip(fn (PendingItem $record): ?string => $this->warningsHelp($record))
                    ->width('9rem')
                    ->wrap(),
                TextColumn::make('authorization_supplier')
                    ->label(__('conciliation.reconciliation.columns.authorization'))
                    ->placeholder(__('conciliation.reconciliation.pending.none'))
                    ->description(fn (PendingItem $record): ?string => $this->authorizationDetails($record))
                    ->action($this->authorizationDetailsAction('openAuthorization'))
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->searchPair($query, $search)),
                TextColumn::make('authorization.amount_cents')
                    ->label(__('conciliation.reconciliation.columns.authorized'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->description(fn (PendingItem $record): ?string => $record->authorization === null
                        ? null
                        : __('conciliation.reconciliation.columns.balance_of', ['amount' => Money::format($record->authorization->balanceCents())]))
                    ->alignEnd(),
                TextColumn::make('payment_supplier')
                    ->label(__('conciliation.reconciliation.columns.payment'))
                    ->state(fn (PendingItem $record): ?string => $record->kind === PendingItemKind::OpenBalance
                        ? trans_choice('conciliation.reconciliation.columns.linked_payments', $record->authorization->links->count(), ['count' => $record->authorization->links->count()])
                        : $record->payment_supplier)
                    ->placeholder(__('conciliation.reconciliation.pending.none'))
                    ->description(fn (PendingItem $record): ?string => $this->paymentDetails($record))
                    ->action($this->paymentDetailsAction('openPayment'))
                    ->wrap(),
                TextColumn::make('payment.amount_cents')
                    ->label(__('conciliation.reconciliation.columns.paid'))
                    ->state(fn (PendingItem $record): ?int => $record->kind === PendingItemKind::OpenBalance
                        ? $record->authorization->state?->paid_cents
                        : $record->payment?->amount_cents)
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->alignEnd(),
                TextColumn::make('difference_cents')
                    ->label(__('conciliation.reconciliation.columns.difference'))
                    ->state(fn (PendingItem $record): ?int => $record->kind === PendingItemKind::OpenBalance
                        ? -$record->authorization->balanceCents()
                        : $record->difference_cents)
                    ->placeholder('')
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->alignEnd(),
            ])
            ->filters([
                ...$this->authorizationFilters('authorization'),
                ...$this->paymentFilters('payment'),
                SelectFilter::make('card')
                    ->label(__('conciliation.reconciliation.columns.card'))
                    ->options(fn (): array => $this->cards())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->where(fn (Builder $query) => $query
                            ->where('authorization_card', $data['value'])
                            ->orWhere('payment_card', $data['value']))),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->searchPlaceholder(__('conciliation.filters.search.pairs'))
            ->recordActions([
                $this->confirmAction(),
                $this->rejectAction(),
                $this->linkAction(),
                $this->linkToAuthorizationAction(),
                $this->createAuthorizationAction(),
                $this->closeWithDiscountAction(),
                $this->authorizationDetailsAction(),
                $this->paymentDetailsAction(),
            ])
            ->toolbarActions([$this->confirmSelectedAction()])
            ->checkIfRecordIsSelectableUsing(fn (PendingItem $record): bool => $record->classification === 'doubtful')
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('conciliation.reconciliation.pending.empty'));
    }

    public function render(): View
    {
        return view('livewire.reconciliation.pending-table', [
            'classifications' => self::CLASSIFICATIONS,
        ]);
    }

    /**
     * @return Builder<PendingItem>
     */
    protected function items(): Builder
    {
        return PendingItem::query()
            ->with(['suggestion.run', 'authorization.state', 'authorization.session', 'authorization.links.payment', 'authorization.plan.items', 'payment'])
            ->where('reconciliation_session_id', $this->sessionId)
            ->when(
                $this->classification === 'todos',
                fn (Builder $query) => $query->where('kind', '<>', PendingItemKind::UnmatchedPayment),
                fn (Builder $query) => $query->where('classification', $this->classification),
            )
            ->orderByRaw("CASE kind WHEN 'suggestion' THEN 0 WHEN 'open_balance' THEN 1 WHEN 'unmatched_authorization' THEN 2 ELSE 3 END")
            ->orderByRaw('COALESCE(score, 0) DESC')
            ->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function cards(): array
    {
        $cards = PendingItem::query()
            ->where('reconciliation_session_id', $this->sessionId)
            ->toBase()
            ->selectRaw('authorization_card as card')
            ->whereNotNull('authorization_card')
            ->union(PendingItem::query()
                ->where('reconciliation_session_id', $this->sessionId)
                ->toBase()
                ->selectRaw('payment_card as card')
                ->whereNotNull('payment_card'))
            ->pluck('card')
            ->unique()
            ->sort()
            ->values();

        return $cards->combine($cards)->all();
    }

    protected function warnings(PendingItem $item): ?string
    {
        $warnings = array_filter([
            $item->suggestion === null ? null : __('conciliation.reconciliation.columns.score_of', ['score' => $item->suggestion->score]),
            $item->suggestion?->is_tie ? __('conciliation.reconciliation.warnings.tie') : null,
            $item->paid_before_authorization ? __('conciliation.reconciliation.warnings.paid_before_authorization') : null,
            $item->card_mismatch ? __('conciliation.reconciliation.warnings.card_mismatch') : null,
            $this->isNotTheForeseenInstallment($item) ? __('conciliation.reconciliation.warnings.not_the_installment') : null,
        ]);

        return $warnings === [] ? null : implode(' · ', $warnings);
    }

    protected function warningsHelp(PendingItem $item): ?string
    {
        $help = array_filter([
            $item->suggestion === null ? null : __('conciliation.reconciliation.columns.axes', [
                'supplier' => $item->suggestion->supplier_score,
                'amount' => $item->suggestion->amount_score,
            ]),
            $item->suggestion?->is_tie ? __('conciliation.reconciliation.warnings.help.tie') : null,
            $item->paid_before_authorization ? __('conciliation.reconciliation.warnings.help.paid_before_authorization') : null,
            $item->card_mismatch ? __('conciliation.reconciliation.warnings.help.card_mismatch') : null,
            $this->isNotTheForeseenInstallment($item) ? __('conciliation.reconciliation.warnings.help.not_the_installment', [
                'amount' => implode(', ', array_map(fn (int $cents): string => Money::format($cents), $this->foreseenAmounts($item))),
            ]) : null,
        ]);

        return $help === [] ? null : implode(' ', $help);
    }

    /**
     * A partial payment for an authorization in instalments whose amount is none of the instalments foreseen.
     */
    protected function isNotTheForeseenInstallment(PendingItem $item): bool
    {
        if ($item->classification !== 'partial' || $item->authorization === null || $item->payment === null) {
            return false;
        }

        $foreseen = $this->foreseenAmounts($item);
        $run = $item->suggestion?->run;

        if ($foreseen === [] || $run === null) {
            return false;
        }

        $parameters = app(EngineParametersFactory::class)->fromRun($run);

        foreach ($foreseen as $amount) {
            if (abs($item->payment->amount_cents - $amount) <= $parameters->toleranceFor($amount)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<int> Amounts of the instalments of the authorization nobody paid yet
     */
    protected function foreseenAmounts(PendingItem $item): array
    {
        return array_values(array_unique(app(InstallmentSchedule::class)->openAmounts(
            app(InstallmentForecaster::class)->installments(
                $item->authorization,
                '',
                $item->suggestion?->run === null ? null : app(EngineParametersFactory::class)->fromRun($item->suggestion->run),
            ),
        )));
    }

    protected function authorizationDetails(PendingItem $item): ?string
    {
        $authorization = $item->authorization;

        if ($authorization === null) {
            return null;
        }

        return implode(' · ', array_filter([
            Str::limit($authorization->request, 70),
            $authorization->authorized_on->format('d/m/Y'),
            $authorization->payment_method,
            $authorization->card === null ? null : __('conciliation.reconciliation.columns.card_number', ['card' => $authorization->card]),
            $authorization->paymentConditionLabel(),
            $authorization->reconciliation_session_id === $this->sessionId
                ? null
                : __('conciliation.reconciliation.columns.from_session', ['period' => $authorization->session->periodLabel()]),
        ]));
    }

    protected function paymentDetails(PendingItem $item): ?string
    {
        if ($item->kind === PendingItemKind::OpenBalance) {
            return $item->authorization->links
                ->map(fn ($link): string => $link->payment->supplier_name.' · '.Money::format($link->payment->amount_cents).' · '.$link->payment->paid_on->format('d/m/Y'))
                ->implode(' | ');
        }

        $payment = $item->payment;

        if ($payment === null) {
            return null;
        }

        $siblings = PaymentEntry::query()
            ->where('reconciliation_session_id', $payment->reconciliation_session_id)
            ->where('unit', $payment->unit)
            ->where('obligation_number', $payment->obligation_number)
            ->where('import_file_id', $payment->import_file_id)
            ->count() - 1;

        return implode(' · ', array_filter([
            $payment->unit->label(),
            $payment->paid_on->format('d/m/Y'),
            $payment->operation_code,
            $payment->species,
            $siblings > 0 ? trans_choice('conciliation.reconciliation.columns.obligation_rows', $siblings, ['count' => $siblings]) : null,
        ]));
    }
}
