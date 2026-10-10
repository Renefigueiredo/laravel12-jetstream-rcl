<?php

namespace App\Livewire\Dashboard;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\RemoveInstallmentPlan;
use App\Actions\Conciliation\RemoveLink;
use App\Actions\Conciliation\SaveInstallmentPlan;
use App\Enums\AuthorizationStatus;
use App\Enums\SessionStatus;
use App\Livewire\Concerns\FiltersByPeriodAndAmount;
use App\Livewire\Reconciliation\Concerns\DecidesPendingItems;
use App\Livewire\Reconciliation\Concerns\ShowsEntryDetails;
use App\Models\AuthorizationEntry;
use App\Models\AuthorizationState;
use App\Models\PaymentEntry;
use App\Models\ReconciliationLink;
use App\Models\ReconciliationSession;
use App\Services\Reconciliation\AuthorizationPanel;
use App\Services\Reconciliation\AuthorizationStatementReader;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\InstallmentForecaster;
use App\Services\Reconciliation\Matching\EngineParameters;
use App\Services\Reconciliation\Matching\InstallmentSchedule;
use App\Services\Reconciliation\Matching\ScheduledInstallment;
use App\Services\Reconciliation\Matching\StatementLine;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\View as ViewLayout;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class AuthorizationsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use DecidesPendingItems;
    use FiltersByPeriodAndAmount;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ShowsEntryDetails;

    public const SCOPES = ['open', 'reconciled'];

    #[Locked]
    public string $scope;

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

    public function mount(string $scope): void
    {
        abort_unless(in_array($scope, self::SCOPES, true), 404);

        $this->scope = $scope;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->authorizations())
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('supplier_name')
                            ->weight(FontWeight::SemiBold)
                            ->searchable(query: fn (Builder $query, string $search): Builder => $this->searchAuthorization($query, $search))
                            ->wrap(),
                        TextColumn::make('request')
                            ->formatStateUsing(fn (AuthorizationEntry $record): string => implode(' · ', array_filter([
                                Str::limit($record->request, 70),
                                $record->authorized_on->format('d/m/Y'),
                                $record->paymentConditionLabel(),
                                $record->card === null ? null : __('conciliation.reconciliation.columns.card_number', ['card' => $record->card]),
                                $record->session->label(),
                                $record->isCreatedInReconciliation() ? __('conciliation.reconciliation.details.created_in_reconciliation') : null,
                            ])))
                            ->color('gray')
                            ->wrap(),
                        TextColumn::make('forecast.overdue_count')
                            ->state(fn (AuthorizationEntry $record): ?string => ($record->forecast?->overdue_count ?? 0) > 0
                                ? trans_choice('conciliation.dashboard.forecast.overdue_count', $record->forecast->overdue_count, ['count' => $record->forecast->overdue_count])
                                : null)
                            ->badge()
                            ->color('danger'),
                    ]),
                    TextColumn::make('status')
                        ->state(fn (AuthorizationEntry $record): AuthorizationStatus => $record->status())
                        ->formatStateUsing(fn (AuthorizationStatus $state): string => $state->label())
                        ->badge()
                        ->color(fn (AuthorizationStatus $state): string => match ($state) {
                            AuthorizationStatus::Reconciled => 'success',
                            AuthorizationStatus::Partial => 'info',
                            AuthorizationStatus::Open => 'gray',
                        })
                        ->grow(false),
                    $this->moneyColumn('amount_cents', 'authorized', fn (AuthorizationEntry $record): int => $record->amount_cents),
                    $this->moneyColumn('paid_cents', 'paid', fn (AuthorizationEntry $record): int => $record->state?->paid_cents ?? 0)
                        ->description(fn (AuthorizationEntry $record): string => __('conciliation.dashboard.columns.paid_in', [
                            'count' => $record->state?->links_count ?? 0,
                        ]), position: 'above'),
                    $this->moneyColumn('balance_cents', 'balance', fn (AuthorizationEntry $record): int => $record->balanceCents())
                        ->weight(FontWeight::SemiBold),
                ])->from('md'),
                Panel::make([
                    ViewLayout::make('livewire.dashboard.partials.statement'),
                ])->collapsible(),
            ])
            ->filters([
                ...$this->authorizationFilters(),
                SelectFilter::make('status')
                    ->label(__('conciliation.dashboard.columns.status'))
                    ->options([
                        AuthorizationStatus::Open->value => AuthorizationStatus::Open->label(),
                        AuthorizationStatus::Partial->value => AuthorizationStatus::Partial->label(),
                    ])
                    ->visible($this->scope === 'open')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        AuthorizationStatus::Open->value => $query->whereDoesntHave('state'),
                        AuthorizationStatus::Partial->value => $query->whereHas('state'),
                        default => $query,
                    }),
                SelectFilter::make('reconciliation_session_id')
                    ->label(__('conciliation.dashboard.columns.session'))
                    ->options(fn (): array => ReconciliationSession::query()
                        ->where('status', SessionStatus::Processed)
                        ->orderByDesc('period')
                        ->get()
                        ->mapWithKeys(fn (ReconciliationSession $session): array => [$session->id => $session->label()])
                        ->all()),
                SelectFilter::make('card')
                    ->label(__('conciliation.reconciliation.columns.card'))
                    ->options(fn (): array => $this->cards()),
                Filter::make('overdue')
                    ->label(__('conciliation.dashboard.filters.overdue'))
                    ->visible($this->scope === 'open')
                    ->query(fn (Builder $query): Builder => app(AuthorizationPanel::class)->overdue($query)),
                Filter::make('overpaid')
                    ->label(__('conciliation.dashboard.filters.overpaid'))
                    ->visible($this->scope === 'reconciled')
                    ->query(fn (Builder $query): Builder => app(AuthorizationPanel::class)->overpaid($query)),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->searchPlaceholder(__('conciliation.filters.search.authorizations'))
            ->recordActions([
                $this->linkAction()->visible(fn (AuthorizationEntry $record): bool => $record->balanceCents() > 0),
                $this->installmentPlanAction(),
                $this->removeInstallmentPlanAction(),
                $this->authorizationDetailsAction(),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('conciliation.dashboard.empty.'.$this->scope));
    }

    /**
     * Totals of what the tab lists, following the search and the filters.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function tabTotals(): array
    {
        return app(AuthorizationPanel::class)->totals($this->getFilteredTableQuery());
    }

    /**
     * First day of the latest month with a processed session, read once for the whole page.
     */
    #[Computed]
    public function latestProcessedMonth(): ?string
    {
        return app(InstallmentForecaster::class)->latestProcessedMonth();
    }

    #[Computed]
    public function parameters(): EngineParameters
    {
        return app(EngineParametersFactory::class)->fromSettings();
    }

    /**
     * Everything the opened line shows: the movements, the instalments foreseen and which
     * payment took which one.
     *
     * @return array{rows: list<array{line: StatementLine, link: ReconciliationLink}>, installments: list<ScheduledInstallment>, positions: array<int, int>, fromPlan: bool}
     */
    public function statementFor(AuthorizationEntry $authorization): array
    {
        $installments = app(InstallmentForecaster::class)->installments($authorization, $this->latestProcessedMonth ?? '', $this->parameters);

        return [
            'rows' => app(AuthorizationStatementReader::class)->read($authorization),
            'installments' => $installments,
            'positions' => app(InstallmentSchedule::class)->positions($installments),
            'fromPlan' => $authorization->plan !== null,
        ];
    }

    protected function installmentPlanAction(): Action
    {
        return Action::make('installmentPlan')
            ->label(fn (AuthorizationEntry $record): string => __('conciliation.dashboard.plan.'.($record->plan === null ? 'inform' : 'change')))
            ->color('gray')
            ->visible(fn (AuthorizationEntry $record): bool => $record->balanceCents() > 0)
            ->modalHeading(__('conciliation.dashboard.plan.heading'))
            ->modalDescription(fn (AuthorizationEntry $record): string => __('conciliation.dashboard.plan.body', [
                'supplier' => $record->supplier_name,
                'amount' => Money::format($record->amount_cents),
            ]))
            ->modalSubmitActionLabel(__('conciliation.settings.save'))
            ->fillForm(fn (AuthorizationEntry $record): array => ['installments' => $record->plan === null
                ? [['amount' => '', 'month' => ''], ['amount' => '', 'month' => '']]
                : $record->plan->items->map(fn ($item): array => [
                    'amount' => number_format($item->amount_cents / 100, 2, ',', '.'),
                    'month' => $item->expected_month?->format('m/Y') ?? '',
                ])->all()])
            ->schema([
                Repeater::make('installments')
                    ->label(__('conciliation.dashboard.plan.installments'))
                    ->schema([
                        TextInput::make('amount')
                            ->label(__('conciliation.dashboard.plan.amount'))
                            ->required()
                            ->regex('/\A\d{1,3}(\.?\d{3})*(,\d{1,2})?\z/')
                            ->validationMessages([
                                'required' => __('conciliation.dashboard.plan.errors.amount'),
                                'regex' => __('conciliation.dashboard.plan.errors.amount'),
                            ]),
                        TextInput::make('month')
                            ->label(__('conciliation.dashboard.plan.month'))
                            ->placeholder('08/2026')
                            ->regex('/\A(0[1-9]|1[0-2])\/\d{4}\z/')
                            ->validationMessages(['regex' => __('conciliation.dashboard.plan.errors.month')]),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->maxItems(SaveInstallmentPlan::MAX_INSTALLMENTS)
                    ->reorderable(false)
                    ->addActionLabel(__('conciliation.dashboard.plan.add')),
            ])
            ->action(fn (AuthorizationEntry $record, array $data) => $this->changePlan(
                fn () => app(SaveInstallmentPlan::class)->handle(auth()->user(), $record, array_map(fn (array $item): array => [
                    'amount_cents' => $this->cents((string) ($item['amount'] ?? '')),
                    'expected_month' => blank($item['month'] ?? null) ? null : substr((string) $item['month'], 3, 4).'-'.substr((string) $item['month'], 0, 2),
                ], array_values($data['installments'] ?? []))),
                __('conciliation.dashboard.plan.saved'),
            ));
    }

    protected function removeInstallmentPlanAction(): Action
    {
        return Action::make('removeInstallmentPlan')
            ->label(__('conciliation.dashboard.plan.remove'))
            ->color('danger')
            ->visible(fn (AuthorizationEntry $record): bool => $record->plan !== null)
            ->requiresConfirmation()
            ->modalHeading(__('conciliation.dashboard.plan.remove_heading'))
            ->modalDescription(__('conciliation.dashboard.plan.remove_body'))
            ->action(fn (AuthorizationEntry $record) => $this->changePlan(
                fn () => app(RemoveInstallmentPlan::class)->handle(auth()->user(), $record),
                __('conciliation.dashboard.plan.removed'),
            ));
    }

    protected function changePlan(callable $change, string $success): void
    {
        try {
            $change();
        } catch (ActionRefusedException $exception) {
            $this->showRefusal($exception->getMessage());

            return;
        }

        $this->refreshList();
        $this->dispatch('reconciliation-changed');
        $this->dispatch('banner-message', style: 'success', message: $success);
    }

    /**
     * Reais typed with a comma, already checked by the form, as integer cents.
     */
    protected function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode(',', str_replace('.', '', $amount), 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function undoLinkAction(): Action
    {
        return Action::make('undoLink')
            ->label(__('conciliation.dashboard.statement.undo'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('conciliation.dashboard.statement.undo_heading'))
            ->modalDescription(fn (array $arguments): string => ReconciliationLink::query()->with('authorization.state')->find($arguments['link'] ?? 0)?->undoingDeletesTheAuthorization()
                ? __('conciliation.dashboard.statement.undo_created_body')
                : __('conciliation.dashboard.statement.undo_body'))
            ->action(fn (array $arguments) => $this->undoLink((int) ($arguments['link'] ?? 0)));
    }

    public function paymentOfLinkAction(): Action
    {
        return Action::make('paymentOfLink')
            ->modalHeading(__('conciliation.reconciliation.details.payment_heading'))
            ->modalContent(function (array $arguments): View {
                $payment = PaymentEntry::query()->with(['session', 'link.authorization'])->findOrFail($arguments['payment'] ?? 0);

                Gate::authorize('view', $payment->session);

                return view('livewire.reconciliation.partials.payment-details', [
                    'payment' => $payment,
                    'siblings' => PaymentEntry::query()
                        ->where('import_file_id', $payment->import_file_id)
                        ->where('obligation_number', $payment->obligation_number)
                        ->whereKeyNot($payment->id)
                        ->orderBy('row_number')
                        ->get(),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('conciliation.reconciliation.details.close'));
    }

    /**
     * Undo one payment of a statement, by the same rules as unlinking on the session screen.
     */
    public function undoLink(int $linkId): void
    {
        $link = ReconciliationLink::query()->find($linkId);

        try {
            if ($link === null) {
                throw new ActionRefusedException(__('conciliation.reconciliation.errors.link_already_removed'));
            }

            app(RemoveLink::class)->handle(auth()->user(), $link);
        } catch (ActionRefusedException $exception) {
            $this->showRefusal($exception->getMessage());
            $this->refreshList();

            return;
        }

        $this->refreshList();
        $this->dispatch('reconciliation-changed');
        $this->dispatch('banner-message', style: 'success', message: __('conciliation.reconciliation.actions.unlinked'));
    }

    #[On('reconciliation-changed')]
    public function refreshList(): void
    {
        unset($this->tabTotals, $this->latestProcessedMonth);
        $this->resetTable();
    }

    public function render(): View
    {
        return view('livewire.dashboard.authorizations-table');
    }

    /**
     * @return Builder<AuthorizationEntry>
     */
    protected function authorizations(): Builder
    {
        $panel = app(AuthorizationPanel::class);

        $query = ($this->scope === 'open' ? $panel->open() : $panel->reconciled())
            ->with(['state', 'session', 'links.payment.session', 'links.decider', 'plan.items', 'forecast']);

        return $this->scope === 'open'
            ? $query
                ->orderByRaw('COALESCE((SELECT f.overdue_count FROM authorization_forecasts f WHERE f.authorization_entry_id = authorization_entries.id), 0) DESC')
                ->orderBy('authorized_on')
                ->orderBy('id')
            : $query->orderByDesc(AuthorizationState::query()
                ->select('updated_at')
                ->whereColumn('authorization_states.authorization_entry_id', 'authorization_entries.id'))->orderByDesc('id');
    }

    protected function moneyColumn(string $name, string $label, callable $state): TextColumn
    {
        return TextColumn::make($name)
            ->state($state)
            ->formatStateUsing(fn (int $state): string => Money::format($state))
            ->description(__('conciliation.dashboard.columns.'.$label), position: 'above')
            ->alignEnd()
            ->grow(false);
    }

    /**
     * @return array<string, string>
     */
    protected function cards(): array
    {
        $cards = app(AuthorizationPanel::class)->query()
            ->whereNotNull('card')
            ->reorder()
            ->distinct()
            ->orderBy('card')
            ->pluck('card');

        return $cards->combine($cards)->all();
    }
}
