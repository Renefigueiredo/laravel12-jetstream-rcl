<?php

namespace App\Livewire\Reconciliation;

use App\Enums\SuggestionStatus;
use App\Models\ReconciliationSession;
use App\Models\ReconciliationSuggestion;
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
use Livewire\Attributes\Locked;
use Livewire\Component;

class EarlyPaymentsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $sessionId;

    public function mount(int $sessionId): void
    {
        $this->authorize('view', ReconciliationSession::query()->findOrFail($sessionId));

        $this->sessionId = $sessionId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ReconciliationSuggestion::query()
                ->with(['authorization', 'payment'])
                ->where('paid_before_authorization', true)
                ->whereIn('status', [SuggestionStatus::Pending, SuggestionStatus::Rejected])
                ->whereHas('run', fn (Builder $query) => $query
                    ->where('reconciliation_session_id', $this->sessionId)
                    ->where('status', 'completed')))
            ->columns([
                TextColumn::make('status')
                    ->label(__('conciliation.reconciliation.columns.situation'))
                    ->badge()
                    ->formatStateUsing(fn (SuggestionStatus $state): string => __('conciliation.reconciliation.early.'.$state->value))
                    ->color(fn (SuggestionStatus $state): string => match ($state) {
                        SuggestionStatus::Confirmed => 'success',
                        SuggestionStatus::Rejected => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('authorization.supplier_name')
                    ->label(__('conciliation.reconciliation.columns.authorization'))
                    ->description(fn (ReconciliationSuggestion $record): string => Money::format($record->authorization->amount_cents))
                    ->wrap(),
                TextColumn::make('authorization.authorized_on')
                    ->label(__('conciliation.reconciliation.columns.authorized_on'))
                    ->date('d/m/Y'),
                TextColumn::make('payment.supplier_name')
                    ->label(__('conciliation.reconciliation.columns.payment'))
                    ->description(fn (ReconciliationSuggestion $record): string => Money::format($record->payment->amount_cents))
                    ->wrap(),
                TextColumn::make('payment.paid_on')
                    ->label(__('conciliation.reconciliation.columns.paid_on'))
                    ->date('d/m/Y'),
                TextColumn::make('score')
                    ->label(__('conciliation.reconciliation.columns.score'))
                    ->alignEnd(),
            ])
            ->defaultSort('score', 'desc')
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('conciliation.reconciliation.early.empty'));
    }

    public function render(): View
    {
        return view('livewire.reconciliation.table');
    }
}
