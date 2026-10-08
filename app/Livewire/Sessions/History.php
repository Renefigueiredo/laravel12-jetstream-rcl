<?php

namespace App\Livewire\Sessions;

use App\Enums\AuditAction;
use App\Models\AuditLog;
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
use Livewire\Attributes\Url;
use Livewire\Component;

class History extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /**
     * @var array<string, mixed>|null
     */
    #[Url(as: 'filtro')]
    public ?array $tableFilters = null;

    public function mount(): void
    {
        $this->authorize('view-session-history');
    }

    public function table(Table $table): Table
    {
        $actions = [AuditAction::SessionDeleted, AuditAction::SessionReopened];

        return $table
            ->query(AuditLog::query()->with('user')->whereIn('action', $actions))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('conciliation.sessions.history.when'))
                    ->dateTime('d/m/Y H:i:s', config('conciliation.display_timezone'))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('conciliation.sessions.history.user')),
                TextColumn::make('action')
                    ->label(__('conciliation.sessions.history.action'))
                    ->badge()
                    ->formatStateUsing(fn (AuditAction $state): string => $state->label())
                    ->color(fn (AuditAction $state): string => $state === AuditAction::SessionDeleted ? 'danger' : 'warning'),
                TextColumn::make('label')
                    ->label(__('conciliation.sessions.history.session')),
                TextColumn::make('files')
                    ->label(__('conciliation.sessions.history.files'))
                    ->state(fn (AuditLog $record): array => array_column($record->before['files'] ?? [], 'file'))
                    ->listWithLineBreaks(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('conciliation.sessions.history.action'))
                    ->options(collect($actions)->mapWithKeys(
                        fn (AuditAction $action): array => [$action->value => $action->label()],
                    )->all()),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('conciliation.sessions.history.empty'));
    }

    public function render(): View
    {
        return view('livewire.sessions.history');
    }
}
