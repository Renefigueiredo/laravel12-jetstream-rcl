<?php

namespace App\Livewire\ExcludedCodes;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\AddExcludedCode;
use App\Actions\Conciliation\RemoveExcludedCode;
use App\Actions\Conciliation\SubmitExcludedCodeImport;
use App\Livewire\Forms\ExcludedCodeForm;
use App\Models\ExcludedCodeImport;
use App\Models\ExcludedOperationCode;
use App\Services\ExcludedCodes\ExcludedOperationCodes;
use App\Services\ExcludedCodes\OperationCode;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Index extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use WithFileUploads;

    /**
     * @var string|null
     */
    #[Url(as: 'busca')]
    public $tableSearch = '';

    #[Url(as: 'ordem')]
    public ?string $tableSort = null;

    public ExcludedCodeForm $form;

    public bool $showingAddModal = false;

    /**
     * @var TemporaryUploadedFile|null
     */
    public $upload = null;

    /**
     * @var array{style: string, text: string}|null
     */
    public ?array $importMessage = null;

    public function mount(): void
    {
        $this->authorize('manage-excluded-codes');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ExcludedOperationCode::query()->with('creator'))
            ->columns([
                TextColumn::make('code')
                    ->label(__('conciliation.excluded_codes.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('conciliation.excluded_codes.description'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('conciliation.excluded_codes.created_at'))
                    ->dateTime('d/m/Y H:i', config('conciliation.display_timezone'))
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label(__('conciliation.excluded_codes.creator')),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label(__('conciliation.excluded_codes.remove.action'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (ExcludedOperationCode $record): string => __('conciliation.excluded_codes.remove.confirm_heading', ['code' => $record->code]))
                    ->modalDescription(__('conciliation.excluded_codes.remove.confirm_body'))
                    ->action(fn (ExcludedOperationCode $record) => $this->removeCode($record->id)),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('conciliation.excluded_codes.empty'));
    }

    /**
     * The latest spreadsheet of codes sent by the user, shown with its progress or its outcome.
     */
    #[Computed]
    public function latestImport(): ?ExcludedCodeImport
    {
        return ExcludedCodeImport::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Whether the page must keep polling for the progress of the queued import.
     */
    #[Computed]
    public function isImporting(): bool
    {
        return (bool) $this->latestImport?->status->isInProgress();
    }

    /**
     * A notice, never a refusal, for a code that no imported payment carries.
     */
    #[Computed]
    public function codeUsageWarning(): ?string
    {
        $code = OperationCode::normalize($this->form->code);

        if ($code === null || ! OperationCode::isValid($code)) {
            return null;
        }

        if (ExcludedOperationCode::query()->where('code', $code)->exists()) {
            return null;
        }

        return app(ExcludedOperationCodes::class)->isUnusedByImportedPayments($code)
            ? __('conciliation.excluded_codes.unused_warning', ['code' => $code])
            : null;
    }

    public function openAddModal(): void
    {
        $this->authorize('manage-excluded-codes');

        $this->form->reset();
        $this->resetErrorBag();
        $this->showingAddModal = true;
    }

    public function save(AddExcludedCode $addExcludedCode): void
    {
        $this->authorize('manage-excluded-codes');

        $this->form->normalize();
        $this->form->validate();

        $unused = $this->codeUsageWarning !== null;

        try {
            $excludedCode = $addExcludedCode->handle(auth()->user(), $this->form->code, $this->form->description);
        } catch (ActionRefusedException $exception) {
            $this->addError('form.code', $exception->getMessage());

            return;
        }

        $this->form->reset();
        $this->showingAddModal = false;

        $this->dispatch(
            'banner-message',
            style: $unused ? 'warning' : 'success',
            message: __($unused ? 'conciliation.excluded_codes.add.done_unused' : 'conciliation.excluded_codes.add.done', ['code' => $excludedCode->code]),
        );
    }

    public function removeCode(int $excludedCodeId, ?RemoveExcludedCode $removeExcludedCode = null): void
    {
        $this->authorize('manage-excluded-codes');

        try {
            $code = ($removeExcludedCode ?? app(RemoveExcludedCode::class))->handle(auth()->user(), $excludedCodeId);
        } catch (ActionRefusedException $exception) {
            $this->dispatch('banner-message', style: 'danger', message: $exception->getMessage());

            return;
        }

        $this->dispatch('banner-message', style: 'success', message: __('conciliation.excluded_codes.remove.done', ['code' => $code]));
    }

    public function updatedUpload(): void
    {
        $this->submitImport();
    }

    public function submitImport(?SubmitExcludedCodeImport $submitExcludedCodeImport = null): void
    {
        $this->authorize('manage-excluded-codes');

        if ($this->upload === null) {
            return;
        }

        $this->importMessage = null;

        try {
            ($submitExcludedCodeImport ?? app(SubmitExcludedCodeImport::class))->handle(auth()->user(), $this->upload);
        } catch (ActionRefusedException $exception) {
            $this->importMessage = ['style' => 'danger', 'text' => $exception->getMessage()];
        } finally {
            $this->upload = null;
            unset($this->latestImport, $this->isImporting);
        }
    }

    public function render(): View
    {
        return view('livewire.excluded-codes.index');
    }
}
