<?php

namespace App\Livewire\Reconciliation\Concerns;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\CloseAuthorizationWithDiscount;
use App\Actions\Conciliation\ConfirmSuggestion;
use App\Actions\Conciliation\CreateMatchingAuthorization;
use App\Actions\Conciliation\LinkManually;
use App\Actions\Conciliation\RejectSuggestion;
use App\Enums\DifferenceTreatment;
use App\Enums\DifferenceType;
use App\Enums\JustificationCategory;
use App\Enums\PendingItemKind;
use App\Livewire\Concerns\ShowsRefusal;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use App\Models\PendingItem;
use App\Models\ReconciliationSession;
use App\Models\ReconciliationSuggestion;
use App\Services\Reconciliation\AuthorizationAvailability;
use App\Services\Reconciliation\DifferenceDecision;
use App\Services\Reconciliation\EngineParametersFactory;
use App\Services\Reconciliation\ReconciliationDecisions;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Row actions of the pending list. Each one delegates to a domain action and reports a refusal
 * with the message the action gives.
 */
trait DecidesPendingItems
{
    use ShowsRefusal;

    protected function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label(__('conciliation.reconciliation.actions.confirm'))
            ->color('success')
            ->visible(fn (PendingItem $record): bool => $record->kind === PendingItemKind::Suggestion)
            ->modalHeading(__('conciliation.reconciliation.actions.confirm_heading'))
            ->modalDescription(fn (PendingItem $record): string => $this->pairDescription($record))
            ->modalSubmitActionLabel(__('conciliation.reconciliation.actions.confirm'))
            ->schema(fn (PendingItem $record): array => $this->decisionFields(match ($record->classification) {
                'partial' => DifferenceType::Partial,
                'excess' => DifferenceType::Excess,
                default => DifferenceType::Exact,
            }))
            ->action(fn (PendingItem $record, array $data) => $this->decide(
                fn () => app(ConfirmSuggestion::class)->handle(
                    auth()->user(),
                    ReconciliationSuggestion::query()->findOrFail($record->suggestion_id),
                    $this->decisionFrom($data),
                ),
                __('conciliation.reconciliation.actions.confirmed'),
            ));
    }

    protected function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('conciliation.reconciliation.actions.reject'))
            ->color('gray')
            ->visible(fn (PendingItem $record): bool => $record->kind === PendingItemKind::Suggestion)
            ->requiresConfirmation()
            ->modalHeading(__('conciliation.reconciliation.actions.reject_heading'))
            ->modalDescription(__('conciliation.reconciliation.actions.reject_body'))
            ->action(fn (PendingItem $record) => $this->decide(
                fn () => app(RejectSuggestion::class)->handle(auth()->user(), ReconciliationSuggestion::query()->findOrFail($record->suggestion_id)),
                __('conciliation.reconciliation.actions.rejected'),
            ));
    }

    protected function linkAction(): Action
    {
        return Action::make('link')
            ->label(__('conciliation.reconciliation.actions.link'))
            ->color('primary')
            ->visible(fn (Model $record): bool => $record instanceof AuthorizationEntry
                || ($record instanceof PendingItem && in_array($record->kind, [PendingItemKind::UnmatchedAuthorization, PendingItemKind::OpenBalance], true)))
            ->modalHeading(__('conciliation.reconciliation.actions.link_heading'))
            ->modalDescription(fn (Model $record): string => __('conciliation.reconciliation.actions.link_body', [
                'supplier' => $this->authorizationOf($record)->supplier_name,
                'balance' => Money::format($this->authorizationOf($record)->balanceCents()),
            ]))
            ->modalSubmitActionLabel(__('conciliation.reconciliation.actions.link'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->schema(fn (Model $record): array => [
                ViewField::make('payments')
                    ->label(__('conciliation.reconciliation.actions.payments'))
                    ->hiddenLabel()
                    ->view('livewire.reconciliation.partials.payment-picker')
                    ->viewData([
                        'payments' => $this->paymentOptions($this->authorizationOf($record)),
                        'balanceCents' => $this->authorizationOf($record)->balanceCents(),
                    ])
                    ->default([])
                    ->required()
                    ->live(),
                ...$this->decisionFields(
                    fn (Get $get): DifferenceType => $this->selectionType($this->authorizationOf($record), (array) $get('payments')),
                ),
            ])
            ->action(fn (Model $record, array $data) => $this->decide(
                fn () => app(LinkManually::class)->handle(
                    auth()->user(),
                    AuthorizationEntry::query()->findOrFail($this->authorizationOf($record)->getKey()),
                    PaymentEntry::query()->whereKey($data['payments'] ?? [])->get()->all(),
                    $this->decisionFrom($data),
                ),
                __('conciliation.reconciliation.actions.linked'),
            ));
    }

    /**
     * The other way round: a payment without authorization is given to an authorization still open.
     */
    protected function linkToAuthorizationAction(): Action
    {
        return Action::make('linkToAuthorization')
            ->label(__('conciliation.reconciliation.actions.link'))
            ->color('primary')
            ->visible(fn (Model $record): bool => $record instanceof PaymentEntry
                || ($record instanceof PendingItem && $record->kind === PendingItemKind::UnmatchedPayment))
            ->modalHeading(__('conciliation.reconciliation.actions.link_to_authorization_heading'))
            ->modalDescription(fn (Model $record): string => __('conciliation.reconciliation.actions.link_to_authorization_body', [
                'supplier' => $this->paymentOf($record)->supplier_name,
                'amount' => Money::format($this->paymentOf($record)->amount_cents),
            ]))
            ->modalSubmitActionLabel(__('conciliation.reconciliation.actions.link'))
            ->schema(fn (Model $record): array => [
                Select::make('authorization')
                    ->label(__('conciliation.reconciliation.actions.open_authorization'))
                    ->helperText(__('conciliation.reconciliation.actions.open_authorization_help'))
                    ->options(fn (): array => $this->authorizationOptions($this->paymentOf($record)))
                    ->searchable()
                    ->required()
                    ->live(),
                ...$this->decisionFields(
                    fn (Get $get): DifferenceType => $this->pairType($get('authorization'), $this->paymentOf($record)),
                ),
            ])
            ->action(fn (Model $record, array $data) => $this->decide(
                fn () => app(LinkManually::class)->handle(
                    auth()->user(),
                    AuthorizationEntry::query()->findOrFail($data['authorization'] ?? null),
                    [PaymentEntry::query()->findOrFail($this->paymentOf($record)->getKey())],
                    $this->decisionFrom($data),
                ),
                __('conciliation.reconciliation.actions.linked'),
            ));
    }

    /**
     * Regularize a payment that had no authorization at all; only for administrators.
     */
    protected function createAuthorizationAction(): Action
    {
        return Action::make('createAuthorization')
            ->label(__('conciliation.reconciliation.actions.create_authorization'))
            ->color('gray')
            ->visible(fn (Model $record): bool => Gate::allows('create-matching-authorization')
                && ($record instanceof PaymentEntry || ($record instanceof PendingItem && $record->kind === PendingItemKind::UnmatchedPayment)))
            ->modalHeading(__('conciliation.reconciliation.actions.create_authorization_heading'))
            ->modalDescription(fn (Model $record): string => __('conciliation.reconciliation.actions.create_authorization_body', [
                'supplier' => $this->paymentOf($record)->supplier_name,
                'amount' => Money::format($this->paymentOf($record)->amount_cents),
                'date' => $this->paymentOf($record)->paid_on->format('d/m/Y'),
            ]))
            ->modalSubmitActionLabel(__('conciliation.reconciliation.actions.create_authorization'))
            ->schema([
                Textarea::make('justification')
                    ->label(__('conciliation.reconciliation.actions.create_authorization_reason'))
                    ->maxLength(CreateMatchingAuthorization::MAX_JUSTIFICATION_LENGTH)
                    ->rows(3)
                    ->required(),
            ])
            ->action(fn (Model $record, array $data) => $this->decide(
                fn () => app(CreateMatchingAuthorization::class)->handle(
                    auth()->user(),
                    PaymentEntry::query()->findOrFail($this->paymentOf($record)->getKey()),
                    (string) ($data['justification'] ?? ''),
                ),
                __('conciliation.reconciliation.actions.authorization_created'),
            ));
    }

    protected function closeWithDiscountAction(): Action
    {
        return Action::make('closeWithDiscount')
            ->label(__('conciliation.reconciliation.actions.close_with_discount'))
            ->color('warning')
            ->visible(fn (PendingItem $record): bool => $record->kind === PendingItemKind::OpenBalance)
            ->modalHeading(__('conciliation.reconciliation.actions.close_with_discount'))
            ->modalDescription(fn (PendingItem $record): string => __('conciliation.reconciliation.actions.close_with_discount_body', [
                'balance' => Money::format($record->authorization->balanceCents()),
            ]))
            ->schema($this->justificationFields())
            ->action(fn (PendingItem $record, array $data) => $this->decide(
                fn () => app(CloseAuthorizationWithDiscount::class)->handle(
                    auth()->user(),
                    AuthorizationEntry::query()->findOrFail($record->authorization_entry_id),
                    JustificationCategory::from($data['category']),
                    (string) $data['justification'],
                ),
                __('conciliation.reconciliation.actions.closed_with_discount'),
            ));
    }

    protected function confirmSelectedAction(): BulkAction
    {
        return BulkAction::make('confirmSelected')
            ->label(__('conciliation.reconciliation.actions.confirm_selected'))
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('conciliation.reconciliation.actions.confirm_selected_body'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $this->authorizeScreenSession();

                $confirmed = 0;
                $refused = 0;

                foreach ($records as $record) {
                    try {
                        app(ConfirmSuggestion::class)->handle(auth()->user(), ReconciliationSuggestion::query()->findOrFail($record->suggestion_id));
                        $confirmed++;
                    } catch (ActionRefusedException) {
                        $refused++;
                    }
                }

                $this->dispatch('reconciliation-changed');
                $this->dispatch(
                    'banner-message',
                    style: $refused === 0 ? 'success' : 'warning',
                    message: __('conciliation.reconciliation.actions.confirmed_selected', ['confirmed' => $confirmed, 'refused' => $refused]),
                );
            });
    }

    /**
     * Run a decision and tell the operator what happened.
     */
    protected function decide(callable $decision, string $success): void
    {
        $this->authorizeScreenSession();

        try {
            $decision();
        } catch (ActionRefusedException $exception) {
            $this->showRefusal($exception->getMessage());
            $this->dispatch('reconciliation-changed');

            return;
        }

        $this->dispatch('reconciliation-changed');
        $this->dispatch('banner-message', style: 'success', message: $success);
    }

    /**
     * Fields that ask what a difference means; none when the amounts are the same.
     *
     * @param  DifferenceType|callable(Get): DifferenceType  $type
     * @return list<Radio|Select|Textarea>
     */
    protected function decisionFields(DifferenceType|callable $type): array
    {
        $resolve = fn (Get $get): DifferenceType => $type instanceof DifferenceType ? $type : $type($get);

        if ($type === DifferenceType::Exact) {
            return [];
        }

        $justified = fn (Get $get): bool => in_array($get('treatment'), [DifferenceTreatment::Discount->value, DifferenceTreatment::AcceptedSurcharge->value], true);

        return [
            Radio::make('treatment')
                ->label(__('conciliation.reconciliation.actions.treatment'))
                ->options(fn (Get $get): array => $resolve($get) === DifferenceType::Excess
                    ? [
                        DifferenceTreatment::Overpayment->value => DifferenceTreatment::Overpayment->label(),
                        DifferenceTreatment::AcceptedSurcharge->value => DifferenceTreatment::AcceptedSurcharge->label(),
                    ]
                    : [
                        DifferenceTreatment::StillOwed->value => DifferenceTreatment::StillOwed->label(),
                        DifferenceTreatment::Discount->value => __('conciliation.reconciliation.actions.close_with_discount'),
                    ])
                ->visible(fn (Get $get): bool => $resolve($get) !== DifferenceType::Exact)
                ->required(fn (Get $get): bool => $resolve($get) !== DifferenceType::Exact)
                ->inline()
                ->live(),
            ...array_map(
                fn (Select|Textarea $field): Select|Textarea => $field
                    ->visible(fn (Get $get): bool => $resolve($get) !== DifferenceType::Exact && $justified($get))
                    ->required(fn (Get $get): bool => $resolve($get) !== DifferenceType::Exact && $justified($get)),
                $this->justificationFields(),
            ),
        ];
    }

    /**
     * @return array{0: Select, 1: Textarea}
     */
    protected function justificationFields(): array
    {
        return [
            Select::make('category')
                ->label(__('conciliation.reconciliation.actions.category'))
                ->options(collect(JustificationCategory::cases())
                    ->reject(fn (JustificationCategory $category): bool => $category === JustificationCategory::WithinTolerance)
                    ->mapWithKeys(fn (JustificationCategory $category): array => [$category->value => $category->label()])
                    ->all())
                ->required(),
            Textarea::make('justification')
                ->label(__('conciliation.reconciliation.actions.justification'))
                ->maxLength(500)
                ->rows(2)
                ->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function decisionFrom(array $data): ?DifferenceDecision
    {
        $treatment = DifferenceTreatment::tryFrom((string) ($data['treatment'] ?? ''));

        if ($treatment === null) {
            return null;
        }

        return new DifferenceDecision(
            $treatment,
            JustificationCategory::tryFrom((string) ($data['category'] ?? '')),
            filled($data['justification'] ?? null) ? (string) $data['justification'] : null,
        );
    }

    /**
     * Free payments of the session, the ones on the card of the authorization first and then
     * the ones closest to what is left to pay.
     *
     * @return list<array{id: int, supplier: string, amount_cents: int, paid_on: string, paid_on_iso: string, card: string|null, session: string|null}>
     */
    protected function paymentOptions(AuthorizationEntry $authorization): array
    {
        $balance = $authorization->balanceCents();
        $onOneSession = property_exists($this, 'sessionId');

        return PaymentEntry::query()
            ->with('session.currentRun')
            ->whereIn('id', PendingItem::query()
                ->when($onOneSession, fn (Builder $query) => $query->where('reconciliation_session_id', $this->sessionId))
                ->when(! $onOneSession, fn (Builder $query) => $query->where('kind', PendingItemKind::UnmatchedPayment))
                ->whereNotNull('payment_entry_id')
                ->select('payment_entry_id'))
            ->get()
            ->filter(fn (PaymentEntry $payment): bool => $onOneSession || $this->mayReceive($authorization, $payment))
            ->sortBy(fn (PaymentEntry $payment): array => [
                $authorization->card !== null && $payment->card === $authorization->card ? 0 : 1,
                abs($payment->amount_cents - $balance),
                $payment->id,
            ])
            ->map(fn (PaymentEntry $payment): array => [
                'id' => $payment->id,
                'supplier' => $payment->supplier_name,
                'amount_cents' => $payment->amount_cents,
                'paid_on' => $payment->paid_on->format('d/m/Y'),
                'paid_on_iso' => $payment->paid_on->toDateString(),
                'card' => $payment->card,
                'session' => $onOneSession ? null : $payment->session->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * Whether the authorization may receive a payment of another session, by the rule of the link action.
     */
    protected function mayReceive(AuthorizationEntry $authorization, PaymentEntry $payment): bool
    {
        $run = $payment->session->currentRun;

        if ($run === null) {
            return false;
        }

        try {
            app(AuthorizationAvailability::class)->assertCanReceive($authorization, $payment, app(EngineParametersFactory::class)->fromRun($run));
        } catch (ActionRefusedException) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, int|string>  $paymentIds
     */
    protected function selectionType(AuthorizationEntry $authorization, array $paymentIds): DifferenceType
    {
        if ($paymentIds === []) {
            return DifferenceType::Exact;
        }

        $run = PaymentEntry::query()->with('session.currentRun')->find($paymentIds[array_key_first($paymentIds)])?->session->currentRun;

        if ($run === null) {
            return DifferenceType::Exact;
        }

        return app(ReconciliationDecisions::class)->compare(
            $authorization->balanceCents(),
            $this->selectionSum($paymentIds),
            app(EngineParametersFactory::class)->fromRun($run),
        )[0];
    }

    /**
     * @param  array<int, int|string>  $paymentIds
     */
    protected function selectionSum(array $paymentIds): int
    {
        return (int) PaymentEntry::query()->whereKey($paymentIds)->sum('amount_cents');
    }

    /**
     * Authorizations of the session list still waiting for payment, the ones on the card of the
     * payment first and then the ones whose balance is closest to it.
     *
     * @return array<int, string>
     */
    protected function authorizationOptions(PaymentEntry $payment): array
    {
        $run = $payment->session->currentRun;

        if ($run === null) {
            return [];
        }

        return app(AuthorizationAvailability::class)
            ->forPayment($payment, app(EngineParametersFactory::class)->fromRun($run))
            ->with(['state', 'session'])
            ->get()
            ->sortBy(fn (AuthorizationEntry $authorization): array => [
                $payment->card !== null && $authorization->card === $payment->card ? 0 : 1,
                abs($authorization->balanceCents() - $payment->amount_cents),
                $authorization->id,
            ])
            ->mapWithKeys(fn (AuthorizationEntry $authorization): array => [
                $authorization->id => implode(' · ', array_filter([
                    $authorization->supplier_name,
                    __('conciliation.reconciliation.columns.balance_of', ['amount' => Money::format($authorization->balanceCents())]),
                    $authorization->authorized_on->format('d/m/Y'),
                    $authorization->card === null ? null : __('conciliation.reconciliation.columns.card_number', ['card' => $authorization->card]),
                    $authorization->reconciliation_session_id === $payment->reconciliation_session_id
                        ? null
                        : __('conciliation.reconciliation.columns.from_session', ['period' => $authorization->session->periodLabel()]),
                ])),
            ])
            ->all();
    }

    protected function pairType(int|string|null $authorizationId, PaymentEntry $payment): DifferenceType
    {
        $authorization = blank($authorizationId) ? null : AuthorizationEntry::query()->with('state')->find($authorizationId);
        $run = $payment->session->currentRun;

        if ($authorization === null || $run === null) {
            return DifferenceType::Exact;
        }

        return app(ReconciliationDecisions::class)->compare(
            $authorization->balanceCents(),
            $payment->amount_cents,
            app(EngineParametersFactory::class)->fromRun($run),
        )[0];
    }

    /**
     * On a screen of one session the user must be allowed to see it; every action also checks
     * the session of the payment it changes.
     */
    protected function authorizeScreenSession(): void
    {
        if (property_exists($this, 'sessionId')) {
            $this->authorize('view', ReconciliationSession::query()->findOrFail($this->sessionId));
        }
    }

    protected function authorizationOf(Model $record): AuthorizationEntry
    {
        return $record instanceof AuthorizationEntry ? $record : $record->authorization;
    }

    protected function paymentOf(Model $record): PaymentEntry
    {
        return $record instanceof PaymentEntry ? $record : $record->payment;
    }

    protected function pairDescription(PendingItem $item): string
    {
        return __('conciliation.reconciliation.actions.pair', [
            'authorization' => $item->authorization->supplier_name,
            'authorized' => Money::format($item->authorization->balanceCents()),
            'payment' => $item->payment->supplier_name,
            'paid' => Money::format($item->payment->amount_cents),
        ]);
    }
}
