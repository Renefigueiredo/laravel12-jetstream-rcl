<?php

namespace App\Livewire\Concerns;

use App\Support\Money;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The two filters every list offers besides its own: a period of dates and a range of amounts.
 */
trait FiltersByPeriodAndAmount
{
    /**
     * The search is split into words; in "R$ 54,00" the symbol is a word that matches nothing.
     */
    protected function isOnlyTheCurrencySymbol(string $search): bool
    {
        return in_array(mb_strtoupper(trim($search)), ['R$'], true);
    }

    /**
     * What a search finds an authorization by: supplier, request text or the exact amount.
     *
     * @return Closure(Builder): Builder To be used on a query of authorizations
     */
    protected function authorizationMatching(string $search): Closure
    {
        return fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
            ->where('supplier_name', 'like', '%'.$search.'%')
            ->orWhere('request', 'like', '%'.$search.'%')
            ->when(Money::parse($search) !== null, fn (Builder $query) => $query->orWhere('amount_cents', Money::parse($search))));
    }

    /**
     * What a search finds a payment by: supplier, operation code or name, or the exact amount.
     *
     * @return Closure(Builder): Builder To be used on a query of payments
     */
    protected function paymentMatching(string $search): Closure
    {
        return fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
            ->where('supplier_name', 'like', '%'.$search.'%')
            ->orWhere('operation_code', 'like', '%'.$search.'%')
            ->orWhere('operation_name', 'like', '%'.$search.'%')
            ->when(Money::parse($search) !== null, fn (Builder $query) => $query->orWhere('amount_cents', Money::parse($search))));
    }

    /**
     * Search of a list of pairs: the authorization or the payment of the row.
     */
    protected function searchPair(Builder $query, string $search): Builder
    {
        if ($this->isOnlyTheCurrencySymbol($search)) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->whereHas('authorization', $this->authorizationMatching($search))
            ->orWhereHas('payment', $this->paymentMatching($search)));
    }

    /**
     * Search of a list whose rows are authorizations, or point to one through a relation.
     */
    protected function searchAuthorization(Builder $query, string $search, ?string $relation = null): Builder
    {
        return match (true) {
            $this->isOnlyTheCurrencySymbol($search) => $query,
            $relation === null => $this->authorizationMatching($search)($query),
            default => $query->whereHas($relation, $this->authorizationMatching($search)),
        };
    }

    /**
     * Search of a list whose rows are payments, or point to one through a relation.
     */
    protected function searchPayment(Builder $query, string $search, ?string $relation = null): Builder
    {
        return match (true) {
            $this->isOnlyTheCurrencySymbol($search) => $query,
            $relation === null => $this->paymentMatching($search)($query),
            default => $query->whereHas($relation, $this->paymentMatching($search)),
        };
    }

    /**
     * The period and amount filters of the authorization of each row.
     *
     * @param  string|null  $relation  Relation that leads to the authorization; null when the row is one
     * @return list<Filter>
     */
    protected function authorizationFilters(?string $relation = null): array
    {
        $on = fn (Builder $query, Closure $condition): Builder => $relation === null ? $condition($query) : $query->whereHas($relation, $condition);

        return [
            $this->periodFilter('authorized', __('conciliation.filters.authorized_on'), fn (Builder $query, string $operator, string $date): Builder => $on($query, fn (Builder $query) => $query->whereDate('authorized_on', $operator, $date))),
            $this->amountFilter('authorized_amount', __('conciliation.filters.authorized_amount'), fn (Builder $query, string $operator, int $cents): Builder => $on($query, fn (Builder $query) => $query->where('amount_cents', $operator, $cents))),
        ];
    }

    /**
     * The period and amount filters of the payment of each row.
     *
     * @param  string|null  $relation  Relation that leads to the payment; null when the row is one
     * @return list<Filter>
     */
    protected function paymentFilters(?string $relation = null): array
    {
        $on = fn (Builder $query, Closure $condition): Builder => $relation === null ? $condition($query) : $query->whereHas($relation, $condition);

        return [
            $this->periodFilter('paid', __('conciliation.filters.paid_on'), fn (Builder $query, string $operator, string $date): Builder => $on($query, fn (Builder $query) => $query->whereDate('paid_on', $operator, $date))),
            $this->amountFilter('paid_amount', __('conciliation.filters.paid_amount'), fn (Builder $query, string $operator, int $cents): Builder => $on($query, fn (Builder $query) => $query->where('amount_cents', $operator, $cents))),
        ];
    }

    /**
     * @param  Closure(Builder, string, string): Builder  $apply  Receives the query, the operator and the date as Y-m-d
     */
    protected function periodFilter(string $name, string $label, Closure $apply): Filter
    {
        return Filter::make($name)
            ->schema([
                DatePicker::make('from')->label(__('conciliation.filters.from', ['field' => $label])),
                DatePicker::make('until')->label(__('conciliation.filters.until', ['field' => $label])),
            ])
            ->columns(2)
            ->columnSpan(2)
            ->query(function (Builder $query, array $data) use ($apply): Builder {
                foreach (['from' => '>=', 'until' => '<='] as $field => $operator) {
                    if (filled($data[$field] ?? null)) {
                        $query = $apply($query, $operator, Carbon::parse($data[$field])->toDateString());
                    }
                }

                return $query;
            })
            ->indicateUsing(fn (array $data): array => array_values(array_filter([
                filled($data['from'] ?? null) ? Indicator::make(__('conciliation.filters.from', ['field' => $label]).': '.Carbon::parse($data['from'])->format('d/m/Y'))->removeField('from') : null,
                filled($data['until'] ?? null) ? Indicator::make(__('conciliation.filters.until', ['field' => $label]).': '.Carbon::parse($data['until'])->format('d/m/Y'))->removeField('until') : null,
            ])));
    }

    /**
     * @param  Closure(Builder, string, int): Builder  $apply  Receives the query, the operator and the amount in cents
     */
    protected function amountFilter(string $name, string $label, Closure $apply): Filter
    {
        return Filter::make($name)
            ->schema([
                TextInput::make('from')->label(__('conciliation.filters.from', ['field' => $label]))->placeholder('0,00')->inputMode('decimal'),
                TextInput::make('until')->label(__('conciliation.filters.until', ['field' => $label]))->placeholder('0,00')->inputMode('decimal'),
            ])
            ->columns(2)
            ->columnSpan(2)
            ->query(function (Builder $query, array $data) use ($apply): Builder {
                foreach (['from' => '>=', 'until' => '<='] as $field => $operator) {
                    $cents = Money::parse($data[$field] ?? null);

                    if ($cents !== null) {
                        $query = $apply($query, $operator, $cents);
                    }
                }

                return $query;
            })
            ->indicateUsing(fn (array $data): array => array_values(array_filter([
                Money::parse($data['from'] ?? null) !== null ? Indicator::make(__('conciliation.filters.from', ['field' => $label]).': '.Money::format(Money::parse($data['from'])))->removeField('from') : null,
                Money::parse($data['until'] ?? null) !== null ? Indicator::make(__('conciliation.filters.until', ['field' => $label]).': '.Money::format(Money::parse($data['until'])))->removeField('until') : null,
            ])));
    }
}
