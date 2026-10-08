<?php

namespace App\Livewire\Sessions;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\ComplementarySessionConfirmationRequired;
use App\Actions\Conciliation\CreateSession;
use App\Actions\Conciliation\DeleteSession;
use App\Enums\ImportSlot;
use App\Enums\SessionStatus;
use App\Models\ImportFile;
use App\Models\ReconciliationSession;
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
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /**
     * @var string|null
     */
    #[Url(as: 'periodo')]
    public $tableSearch = '';

    /**
     * @var array<string, mixed>|null
     */
    #[Url(as: 'situacao')]
    public ?array $tableFilters = null;

    public bool $showingCreateModal = false;

    public string $period = '';

    public ?string $complementaryWarning = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ReconciliationSession::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ReconciliationSession::query()->with(['creator', 'activeFiles']))
            ->columns([
                TextColumn::make('id')
                    ->label(__('conciliation.sessions.number'))
                    ->sortable(),
                TextColumn::make('period')
                    ->label(__('conciliation.sessions.period'))
                    ->formatStateUsing(fn (ReconciliationSession $record): string => $record->periodLabel())
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->searchPeriod($query, $search)),
                TextColumn::make('status')
                    ->label(__('conciliation.sessions.status_label'))
                    ->badge()
                    ->formatStateUsing(fn (SessionStatus $state): string => $state->label())
                    ->color(fn (SessionStatus $state): string => match ($state) {
                        SessionStatus::Open => 'gray',
                        SessionStatus::Processing => 'warning',
                        SessionStatus::Processed => 'success',
                    }),
                ...array_map(fn (ImportSlot $slot): TextColumn => $this->slotColumn($slot), ImportSlot::cases()),
                TextColumn::make('creator.name')
                    ->label(__('conciliation.sessions.creator')),
                TextColumn::make('created_at')
                    ->label(__('conciliation.sessions.created_at'))
                    ->dateTime('d/m/Y H:i', config('conciliation.display_timezone'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('conciliation.sessions.status_label'))
                    ->options(collect(SessionStatus::cases())->mapWithKeys(
                        fn (SessionStatus $status): array => [$status->value => $status->label()],
                    )->all()),
            ])
            ->recordActions([
                Action::make('delete')
                    ->label(__('conciliation.sessions.delete.action'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('conciliation.sessions.delete.confirm_heading'))
                    ->modalDescription(__('conciliation.sessions.delete.confirm_body'))
                    ->visible(fn (ReconciliationSession $record): bool => $record->isOpen() && ! $record->hasEverBeenProcessed())
                    ->action(fn (ReconciliationSession $record) => $this->deleteSession($record->id)),
            ])
            ->recordUrl(fn (ReconciliationSession $record): string => route('sessions.show', $record))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('conciliation.sessions.empty'));
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', ReconciliationSession::class);

        $this->reset('period', 'complementaryWarning');
        $this->resetErrorBag();
        $this->showingCreateModal = true;
    }

    public function updatedPeriod(): void
    {
        $this->complementaryWarning = null;
    }

    /**
     * Create the session; a period that already has a session needs a second, confirming call.
     */
    public function createSession(CreateSession $createSession): void
    {
        $this->authorize('create', ReconciliationSession::class);

        try {
            $session = $createSession->handle(auth()->user(), $this->period, $this->complementaryWarning !== null);
        } catch (ComplementarySessionConfirmationRequired $exception) {
            $this->complementaryWarning = $exception->getMessage();

            return;
        }

        session()->flash('flash.banner', __('conciliation.sessions.created'));

        $this->redirectRoute('sessions.show', $session);
    }

    public function deleteSession(int $sessionId, ?DeleteSession $deleteSession = null): void
    {
        $session = ReconciliationSession::query()->find($sessionId);

        if ($session === null) {
            $this->dispatch('banner-message', style: 'danger', message: __('conciliation.sessions.not_found'));

            return;
        }

        try {
            ($deleteSession ?? app(DeleteSession::class))->handle(auth()->user(), $session);
        } catch (ActionRefusedException $exception) {
            $this->dispatch('banner-message', style: 'danger', message: $exception->getMessage());

            return;
        }

        $this->dispatch('banner-message', style: 'success', message: __('conciliation.sessions.delete.done'));
    }

    public function render(): View
    {
        return view('livewire.sessions.index');
    }

    protected function slotColumn(ImportSlot $slot): TextColumn
    {
        return TextColumn::make('slot_'.$slot->value)
            ->label($slot->label())
            ->state(fn (ReconciliationSession $record): bool => $record->activeFiles->contains(
                fn (ImportFile $file): bool => $file->slot === $slot,
            ))
            ->badge()
            ->formatStateUsing(fn (bool $state): string => __('conciliation.slots.status.'.($state ? 'loaded' : 'pending')))
            ->color(fn (bool $state): string => $state ? 'success' : 'gray');
    }

    /**
     * Search by "MM/AAAA", by year or by month.
     *
     * @param  Builder<ReconciliationSession>  $query
     * @return Builder<ReconciliationSession>
     */
    protected function searchPeriod(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $search, $matches) === 1) {
            return $query->whereYear('period', (int) $matches[2])->whereMonth('period', (int) $matches[1]);
        }

        if (preg_match('/^\d{4}$/', $search) === 1) {
            return $query->whereYear('period', (int) $search);
        }

        if (preg_match('/^\d{1,2}$/', $search) === 1) {
            return $query->whereMonth('period', (int) $search);
        }

        return $query->whereRaw('1 = 0');
    }
}
