<?php

namespace App\Livewire\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\RemoveLink;
use App\Enums\DifferenceTreatment;
use App\Enums\LinkOrigin;
use App\Livewire\Concerns\ShowsRefusal;
use App\Livewire\Reconciliation\Concerns\ShowsEntryDetails;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSession;
use App\Support\Money;
use Filament\Actions\Action;
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
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class LinksTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ShowsEntryDetails;
    use ShowsRefusal;

    #[Locked]
    public int $sessionId;

    #[Locked]
    public bool $onlyPaidBefore = false;

    #[Locked]
    public ?string $card = null;

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

    public function mount(int $sessionId, bool $onlyPaidBefore = false, ?string $card = null): void
    {
        $this->authorize('view', ReconciliationSession::query()->findOrFail($sessionId));

        $this->sessionId = $sessionId;
        $this->onlyPaidBefore = $onlyPaidBefore;
        $this->card = $card;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ReconciliationLink::query()
                ->with(['authorization.state', 'authorization.session', 'payment.session', 'run'])
                ->when($this->onlyPaidBefore, fn (Builder $query) => $query->where('paid_before_authorization', true))
                ->when($this->card !== null, fn (Builder $query) => $query->whereHas('payment', fn (Builder $query) => $query
                    ->where('reconciliation_session_id', $this->sessionId)
                    ->where('card', $this->card)))
                ->where(fn (Builder $query) => $query
                    ->whereHas('payment', fn (Builder $query) => $query->where('reconciliation_session_id', $this->sessionId))
                    ->orWhereHas('authorization', fn (Builder $query) => $query->where('reconciliation_session_id', $this->sessionId))))
            ->columns([
                TextColumn::make('origin')
                    ->label(__('conciliation.reconciliation.columns.origin'))
                    ->badge()
                    ->state(fn (ReconciliationLink $record): string => match (true) {
                        $record->authorization->isCreatedInReconciliation() => 'created',
                        $record->is_installment => 'installment',
                        $record->origin === LinkOrigin::Manual => 'manual',
                        default => 'automatic',
                    })
                    ->formatStateUsing(fn (string $state): string => __('conciliation.reconciliation.link_origin.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'danger',
                        'manual' => 'warning',
                        default => 'success',
                    })
                    ->description(fn (ReconciliationLink $record): ?string => $this->warnings($record))
                    ->tooltip(fn (ReconciliationLink $record): ?string => match (true) {
                        $record->authorization->isCreatedInReconciliation() => __('conciliation.reconciliation.link_origin.created_help'),
                        $record->score === null => null,
                        default => __('conciliation.reconciliation.columns.axes', [
                            'supplier' => $record->supplier_score,
                            'amount' => $record->amount_score,
                        ]),
                    })
                    ->width('9rem')
                    ->wrap(),
                TextColumn::make('authorization.supplier_name')
                    ->label(__('conciliation.reconciliation.columns.authorization'))
                    ->action($this->authorizationDetailsAction('openAuthorization'))
                    ->description(fn (ReconciliationLink $record): string => implode(' · ', array_filter([
                        Str::limit($record->authorization->request, 70),
                        $record->authorization->authorized_on->format('d/m/Y'),
                        $record->authorization->payment_method,
                        $record->authorization->card === null ? null : __('conciliation.reconciliation.columns.card_number', ['card' => $record->authorization->card]),
                        $record->authorization->isCreatedInReconciliation() ? __('conciliation.reconciliation.details.created_in_reconciliation') : null,
                        $record->authorization->reconciliation_session_id === $this->sessionId
                            ? null
                            : __('conciliation.reconciliation.columns.from_session', ['period' => $record->authorization->session->periodLabel()]),
                    ])))
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->whereHas('authorization', fn (Builder $query) => $query->where('supplier_name', 'like', '%'.$search.'%'))
                        ->orWhereHas('payment', fn (Builder $query) => $query->where('supplier_name', 'like', '%'.$search.'%')))),
                TextColumn::make('authorization.amount_cents')
                    ->label(__('conciliation.reconciliation.columns.authorized'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->description(fn (ReconciliationLink $record): string => __('conciliation.reconciliation.columns.balance_of', [
                        'amount' => Money::format($record->authorization->balanceCents()),
                    ]))
                    ->alignEnd(),
                TextColumn::make('payment.supplier_name')
                    ->label(__('conciliation.reconciliation.columns.payment'))
                    ->action($this->paymentDetailsAction('openPayment'))
                    ->description(fn (ReconciliationLink $record): string => implode(' · ', array_filter([
                        $record->payment->unit->label(),
                        $record->payment->paid_on->format('d/m/Y'),
                        $record->payment->species,
                        $record->payment->reconciliation_session_id === $this->sessionId
                            ? null
                            : __('conciliation.reconciliation.columns.paid_in_session', ['period' => $record->payment->session->periodLabel()]),
                    ])))
                    ->wrap(),
                TextColumn::make('payment.amount_cents')
                    ->label(__('conciliation.reconciliation.columns.paid'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->alignEnd(),
                TextColumn::make('difference_cents')
                    ->label(__('conciliation.reconciliation.columns.difference'))
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? '' : Money::format($state))
                    ->badge(fn (ReconciliationLink $record): bool => $this->hasNotableDifference($record))
                    ->color('warning')
                    ->description(fn (ReconciliationLink $record): ?string => $this->hasNotableDifference($record)
                        ? __('conciliation.reconciliation.links.'.($record->difference_cents < 0 ? 'paid_less' : 'paid_more'))
                        : null)
                    ->alignEnd(),
                TextColumn::make('treatment')
                    ->label(__('conciliation.reconciliation.columns.treatment'))
                    ->placeholder('')
                    ->badge()
                    ->formatStateUsing(fn (DifferenceTreatment $state): string => $state->label())
                    ->color(fn (DifferenceTreatment $state): string => $state === DifferenceTreatment::Overpayment ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('origin')
                    ->label(__('conciliation.reconciliation.columns.origin'))
                    ->options([
                        'automatic' => __('conciliation.reconciliation.link_origin.automatic'),
                        'manual' => __('conciliation.reconciliation.link_origin.manual'),
                        'installment' => __('conciliation.reconciliation.link_origin.installment'),
                        'created' => __('conciliation.reconciliation.link_origin.created'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'automatic' => $query->whereNull('decided_by')->where('is_installment', false),
                        'manual' => $query->whereNotNull('decided_by'),
                        'installment' => $query->where('is_installment', true),
                        'created' => $query->whereHas('authorization', fn (Builder $query) => $query->whereNull('import_file_id')),
                        default => $query,
                    }),
                SelectFilter::make('difference')
                    ->label(__('conciliation.reconciliation.columns.difference'))
                    ->options([
                        'with' => __('conciliation.reconciliation.links.with_difference'),
                        'less' => __('conciliation.reconciliation.links.paid_less'),
                        'more' => __('conciliation.reconciliation.links.paid_more'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with' => $this->withNotableDifference($query),
                        'less' => $this->withNotableDifference($query)->where('reconciliation_links.difference_cents', '<', 0),
                        'more' => $this->withNotableDifference($query)->where('reconciliation_links.difference_cents', '>', 0),
                        default => $query,
                    }),
                SelectFilter::make('treatment')
                    ->label(__('conciliation.reconciliation.columns.treatment'))
                    ->options(collect(DifferenceTreatment::cases())->mapWithKeys(
                        fn (DifferenceTreatment $treatment): array => [$treatment->value => $treatment->label()],
                    )->all()),
                SelectFilter::make('card')
                    ->label(__('conciliation.reconciliation.columns.card'))
                    ->options(fn (): array => $this->cards())
                    ->visible($this->card === null)
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->where(fn (Builder $query) => $query
                            ->whereHas('authorization', fn (Builder $query) => $query->where('card', $data['value']))
                            ->orWhereHas('payment', fn (Builder $query) => $query->where('card', $data['value'])))),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('unlink')
                    ->label(__('conciliation.reconciliation.actions.unlink'))
                    ->color('danger')
                    ->visible(fn (ReconciliationLink $record): bool => $record->payment->reconciliation_session_id === $this->sessionId)
                    ->requiresConfirmation()
                    ->modalHeading(__('conciliation.reconciliation.actions.unlink_heading'))
                    ->modalDescription(__('conciliation.reconciliation.actions.unlink_body'))
                    ->action(fn (ReconciliationLink $record) => $this->unlink($record->id)),
                $this->authorizationDetailsAction(),
                $this->paymentDetailsAction(),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('conciliation.reconciliation.links.empty'));
    }

    /**
     * Another list on the same screen linked or unlinked something.
     */
    #[On('reconciliation-changed')]
    public function refreshLinks(): void
    {
        $this->resetTable();
    }

    public function unlink(int $linkId): void
    {
        $this->authorize('view', ReconciliationSession::query()->findOrFail($this->sessionId));

        $link = ReconciliationLink::query()->with('payment')->find($linkId);

        try {
            if ($link === null || $link->payment->reconciliation_session_id !== $this->sessionId) {
                throw new ActionRefusedException(__('conciliation.reconciliation.errors.link_already_removed'));
            }

            app(RemoveLink::class)->handle(auth()->user(), $link);
        } catch (ActionRefusedException $exception) {
            $this->showRefusal($exception->getMessage());

            return;
        }

        $this->dispatch('reconciliation-changed');
        $this->dispatch('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.unlinked'));
    }

    public function render(): View
    {
        return view('livewire.reconciliation.table');
    }

    /**
     * @return array<string, string>
     */
    protected function cards(): array
    {
        $cards = ReconciliationSession::query()
            ->findOrFail($this->sessionId)
            ->cards();

        return array_combine($cards, $cards);
    }

    /**
     * A difference worth showing: accepted as the same amount, yet above the fixed tolerance of the run.
     */
    protected function hasNotableDifference(ReconciliationLink $link): bool
    {
        return $link->treatment === null && abs($link->difference_cents) > $link->run->tolerance_cents;
    }

    /**
     * @param  Builder<ReconciliationLink>  $query
     * @return Builder<ReconciliationLink>
     */
    protected function withNotableDifference(Builder $query): Builder
    {
        return $query
            ->whereNull('reconciliation_links.treatment')
            ->whereRaw('ABS(reconciliation_links.difference_cents) > (SELECT r.tolerance_cents FROM reconciliation_runs r WHERE r.id = reconciliation_links.reconciliation_run_id)');
    }

    protected function warnings(ReconciliationLink $link): ?string
    {
        $warnings = array_filter([
            $link->score === null ? null : __('conciliation.reconciliation.columns.score_of', ['score' => $link->score]),
            $link->paid_before_authorization ? __('conciliation.reconciliation.warnings.paid_before_authorization') : null,
            $link->card_mismatch ? __('conciliation.reconciliation.warnings.card_mismatch') : null,
        ]);

        return $warnings === [] ? null : implode(' · ', $warnings);
    }
}
