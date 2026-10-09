<?php

namespace App\Livewire\Reconciliation;

use App\Enums\SkipReason;
use App\Models\ReconciliationSession;
use App\Models\ReconciliationSkip;
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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class ExcludedTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $sessionId;

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

    /**
     * The codes in effect when the session was run, each with the payments it left out.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function codes(): array
    {
        $run = ReconciliationSession::query()->findOrFail($this->sessionId)->currentRun;

        if ($run === null) {
            return [];
        }

        $counts = ReconciliationSkip::query()
            ->where('reconciliation_run_id', $run->id)
            ->where('reason', SkipReason::ExcludedCode)
            ->toBase()
            ->selectRaw('operation_code, COUNT(*) as payments')
            ->groupBy('operation_code')
            ->pluck('payments', 'operation_code');

        $codes = [];

        foreach ($run->excluded_codes as $code) {
            $codes[(string) $code] = (int) ($counts[$code] ?? 0);
        }

        arsort($codes);

        return $codes;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ReconciliationSkip::query()
                ->with('payment')
                ->where('reason', SkipReason::ExcludedCode)
                ->whereHas('run', fn (Builder $query) => $query
                    ->where('reconciliation_session_id', $this->sessionId)
                    ->where('status', 'completed')))
            ->columns([
                TextColumn::make('operation_code')
                    ->label(__('conciliation.excluded_codes.code'))
                    ->description(fn (ReconciliationSkip $record): ?string => $record->payment?->operation_name),
                TextColumn::make('payment.supplier_name')
                    ->label(__('conciliation.reconciliation.columns.payment'))
                    ->description(fn (ReconciliationSkip $record): string => implode(' · ', array_filter([
                        $record->payment->unit->label(),
                        $record->payment->paid_on->format('d/m/Y'),
                        $record->payment->species,
                    ])))
                    ->wrap(),
                TextColumn::make('payment.amount_cents')
                    ->label(__('conciliation.reconciliation.columns.paid'))
                    ->formatStateUsing(fn (?int $state): string => Money::format($state))
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('operation_code')
                    ->label(__('conciliation.excluded_codes.code'))
                    ->options(fn (): array => collect(array_keys(array_filter($this->codes)))
                        ->mapWithKeys(fn (string|int $code): array => [(string) $code => (string) $code])
                        ->all()),
            ])
            ->defaultSort('id')
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('conciliation.reconciliation.excluded.empty'));
    }

    public function render(): View
    {
        return view('livewire.reconciliation.excluded-table');
    }
}
