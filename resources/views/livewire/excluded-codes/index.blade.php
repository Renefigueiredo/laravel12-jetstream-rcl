@php
    use App\Enums\ExcludedCodeImportStatus;

    $import = $this->latestImport;
    $importStatus = $import?->status;
@endphp

<div @if ($this->isImporting) wire:poll.2s @endif>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('conciliation.excluded_codes.title') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col gap-6">
            <div class="flex flex-wrap items-center justify-between gap-4 px-4 sm:px-0">
                <p class="text-sm text-gray-700">{{ __('conciliation.excluded_codes.intro') }}</p>

                <x-button type="button" wire:click="openAddModal">
                    {{ __('conciliation.excluded_codes.add.action') }}
                </x-button>
            </div>

            <section aria-labelledby="excluded-codes-import-heading" class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-col gap-4">
                <h3 id="excluded-codes-import-heading" class="text-base font-semibold text-gray-900">
                    {{ __('conciliation.excluded_codes.import.heading') }}
                </h3>

                @if ($importMessage)
                    <p role="alert" class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">{{ $importMessage['text'] }}</p>
                @endif

                @if ($importStatus?->isInProgress())
                    <div role="status" class="flex flex-col gap-1 text-sm text-gray-900">
                        <p class="font-semibold">{{ __('conciliation.excluded_codes.import.in_progress') }}</p>
                        <p class="break-all text-gray-700">{{ $import->original_name }}</p>
                    </div>
                @elseif ($importStatus === ExcludedCodeImportStatus::Completed)
                    <div role="status" class="flex flex-col gap-1 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-900">
                        <p class="font-semibold">
                            {{ __('conciliation.excluded_codes.import.completed', [
                                'added' => trans_choice('conciliation.excluded_codes.import.added', $import->added_count, ['count' => $import->added_count]),
                                'ignored' => trans_choice('conciliation.excluded_codes.import.ignored', $import->ignored_count, ['count' => $import->ignored_count]),
                            ]) }}
                        </p>
                        <p class="break-all">{{ $import->original_name }}</p>
                    </div>
                @elseif ($importStatus === ExcludedCodeImportStatus::Rejected)
                    <div role="alert" class="flex flex-col gap-2 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
                        <p class="font-semibold">{{ __('conciliation.excluded_codes.import.rejected') }}</p>
                        <p class="break-all">{{ $import->original_name }}</p>

                        @if ($import->failure_message)
                            <p>{{ $import->failure_message }}</p>
                        @endif

                        @if ($import->errors)
                            <p>{{ trans_choice('conciliation.excluded_codes.import.error_total', count($import->errors), ['count' => count($import->errors)]) }}</p>
                            <div class="max-h-64 overflow-auto" tabindex="0">
                                <table class="min-w-full text-left text-xs">
                                    <thead>
                                        <tr>
                                            <th scope="col" class="pe-3 py-1">{{ __('conciliation.excluded_codes.import.row') }}</th>
                                            <th scope="col" class="pe-3 py-1">{{ __('conciliation.excluded_codes.import.column') }}</th>
                                            <th scope="col" class="py-1">{{ __('conciliation.excluded_codes.import.reason') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($import->errors as $index => $error)
                                            <tr wire:key="import-error-{{ $import->id }}-{{ $index }}" class="border-t border-red-200 align-top">
                                                <td class="pe-3 py-1">{{ $error['row'] }}</td>
                                                <td class="pe-3 py-1">{{ $error['column'] }}</td>
                                                <td class="py-1">{{ $error['reason'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @elseif ($importStatus === ExcludedCodeImportStatus::Failed)
                    <p role="alert" class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
                        {{ $import->failure_message ?? __('conciliation.excluded_codes.import.failed') }}
                    </p>
                @endif

                @unless ($this->isImporting)
                    <div class="flex flex-col gap-2">
                        <label for="excluded-codes-upload" class="text-sm font-medium text-gray-900">
                            {{ __('conciliation.excluded_codes.import.choose_file') }}
                        </label>
                        <input id="excluded-codes-upload" type="file" accept=".csv,.xlsx" wire:model="upload" aria-describedby="excluded-codes-upload-help"
                            class="block w-full text-sm text-gray-900 file:me-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-4 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md" />
                        <p id="excluded-codes-upload-help" class="text-sm text-gray-600">
                            {{ __('conciliation.excluded_codes.import.help', ['max' => config('conciliation.excluded_codes.max_size_mb')]) }}
                        </p>
                        <p wire:loading wire:target="upload" role="status" class="text-sm text-gray-700">
                            {{ __('conciliation.excluded_codes.import.uploading') }}
                        </p>
                        <x-input-error for="upload" />
                    </div>
                @endunless

                <div class="text-sm">
                    <a href="{{ route('excluded-codes.template') }}" class="text-gray-800 underline hover:text-gray-900 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md">
                        {{ __('conciliation.excluded_codes.import.download_template') }}
                    </a>
                </div>
            </section>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                {{ $this->table }}
            </div>
        </div>
    </div>

    <x-dialog-modal wire:model.live="showingAddModal" maxWidth="md">
        <x-slot name="title">
            {{ __('conciliation.excluded_codes.add.action') }}
        </x-slot>

        <x-slot name="content">
            <form wire:submit="save" id="add-excluded-code-form" class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <x-label for="excluded-code" value="{{ __('conciliation.excluded_codes.code') }}" />
                    <x-input id="excluded-code" type="text" class="block w-full" maxlength="40" autocomplete="off"
                        aria-describedby="excluded-code-help" wire:model.live.debounce.400ms="form.code" />
                    <p id="excluded-code-help" class="text-sm text-gray-600">{{ __('conciliation.excluded_codes.code_help') }}</p>
                    <x-input-error for="form.code" />

                    @if ($this->codeUsage)
                        <div role="status" class="flex flex-col gap-2 rounded-md border border-gray-300 bg-gray-50 p-3 text-sm text-gray-900">
                            <p>
                                <span class="font-semibold">{{ __('conciliation.excluded_codes.erp.heading') }}</span>
                                {{ $this->codeUsage['name'] ?? __('conciliation.excluded_codes.erp.unnamed') }}
                            </p>
                            <p>{{ trans_choice('conciliation.excluded_codes.erp.payments', $this->codeUsage['payments'], ['count' => $this->codeUsage['payments']]) }}</p>

                            @if (filled($this->codeUsage['name']))
                                <button type="button" wire:click="useErpNameAsDescription"
                                    class="self-start underline text-indigo-800 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md">
                                    {{ __('conciliation.excluded_codes.erp.use_as_description') }}
                                </button>
                            @endif
                        </div>
                    @endif

                    @if ($this->codeUsageWarning)
                        <p role="status" class="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">
                            {{ $this->codeUsageWarning }}
                        </p>
                    @endif
                </div>

                <div class="flex flex-col gap-2">
                    <x-label for="excluded-code-description" value="{{ __('conciliation.excluded_codes.description') }}" />
                    <x-input id="excluded-code-description" type="text" class="block w-full" autocomplete="off"
                        aria-describedby="excluded-code-description-help" wire:model="form.description" />
                    <p id="excluded-code-description-help" class="text-sm text-gray-600">{{ __('conciliation.excluded_codes.description_help') }}</p>
                    <x-input-error for="form.description" />
                </div>
            </form>
        </x-slot>

        <x-slot name="footer">
            <div class="flex gap-3">
                <x-secondary-button type="button" wire:click="$set('showingAddModal', false)">
                    {{ __('conciliation.excluded_codes.cancel') }}
                </x-secondary-button>

                <x-button type="submit" form="add-excluded-code-form" wire:loading.attr="disabled">
                    {{ __('conciliation.excluded_codes.add.save') }}
                </x-button>
            </div>
        </x-slot>
    </x-dialog-modal>

    <x-filament-actions::modals />
</div>
