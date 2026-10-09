@php
    use App\Enums\SessionStatus;

    $missingSlots = $session->missingSlots();
    $isOpen = $session->status === SessionStatus::Open;
    $canExecute = $isOpen && $missingSlots === [] && $engineEnabled;
    $canDelete = $isOpen && ! $session->hasEverBeenProcessed();
@endphp

<div @if ($this->isWorking) wire:poll.2s @endif>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('conciliation.sessions.title') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-6">
            <div class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-col gap-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h3 class="text-lg font-semibold text-gray-900">{{ $session->label() }}</h3>
                        <p class="text-sm text-gray-600">
                            {{ __('conciliation.sessions.status_label') }}:
                            <span class="font-semibold text-gray-900">{{ $session->status->label() }}</span>
                        </p>
                        <p class="text-sm text-gray-600">
                            {{ __('conciliation.sessions.creator') }} {{ $session->creator->name }},
                            {{ $session->created_at->timezone($timezone)->format('d/m/Y H:i') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('sessions.index') }}" class="text-sm text-gray-700 underline hover:text-gray-900 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md">
                            {{ __('conciliation.sessions.back') }}
                        </a>

                        @if ($session->status === SessionStatus::Processed)
                            <a href="{{ route('reconciliation.show', $session) }}" class="inline-flex items-center rounded-md bg-indigo-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-600 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500">
                                {{ __('conciliation.reconciliation.open') }}
                            </a>

                            <x-secondary-button type="button" wire:click="$set('confirmingReopen', true)">
                                {{ __('conciliation.sessions.reopen.action') }}
                            </x-secondary-button>
                        @endif

                        @if ($canDelete)
                            <x-danger-button type="button" wire:click="$set('confirmingDeletion', true)">
                                {{ __('conciliation.sessions.delete.action') }}
                            </x-danger-button>
                        @endif

                        @if ($isOpen)
                            <x-button type="button" wire:click="$set('confirmingExecution', true)" :disabled="! $canExecute" aria-describedby="execute-help">
                                {{ __('conciliation.sessions.execute.action') }}
                            </x-button>
                        @endif
                    </div>
                </div>

                <div id="execute-help" class="flex flex-col gap-2 text-sm">
                    @if ($isOpen && $missingSlots !== [])
                        <p class="text-gray-700">
                            {{ __('conciliation.sessions.execute.missing', ['slots' => implode(', ', array_map(fn ($slot) => $slot->label(), $missingSlots))]) }}
                        </p>
                    @endif

                    @if ($isOpen && ! $engineEnabled)
                        <p class="text-gray-700">{{ __('conciliation.sessions.execute.engine_disabled') }}</p>
                    @endif

                    @if ($session->status === SessionStatus::Processed && $session->currentRun?->totals)
                        <p class="text-gray-900">
                            {{ __('conciliation.reconciliation.panel_summary', [
                                'automatic' => $session->currentRun->totals['reconciled_automatically'],
                                'authorizations' => $session->currentRun->totals['authorizations'],
                                'percent' => $session->currentRun->totals['automatic_percent'],
                                'awaiting' => $session->currentRun->totals['awaiting_decision'],
                                'when' => $session->currentRun->finished_at->timezone($timezone)->format('d/m/Y H:i'),
                            ]) }}
                        </p>
                    @endif

                    @if ($session->status === SessionStatus::Processing)
                        <p role="status" class="font-semibold text-gray-900">
                            {{ __('conciliation.sessions.execute.progress', ['percent' => $session->progress ?? 0]) }}
                        </p>
                        <progress class="w-full" max="100" value="{{ $session->progress ?? 0 }}"
                            aria-label="{{ __('conciliation.sessions.execute.progress', ['percent' => $session->progress ?? 0]) }}"></progress>
                    @endif

                    @if (! $isOpen)
                        <p class="text-gray-700">{{ __('conciliation.sessions.execute.locked_notice') }}</p>
                    @endif

                    @if ($session->result_stale)
                        <p role="alert" class="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-yellow-900">
                            {{ __('conciliation.sessions.reopen.stale') }}
                        </p>
                    @endif

                    @if ($isOpen && $session->last_failure)
                        <p role="alert" class="rounded-md border border-red-300 bg-red-50 p-3 text-red-900">
                            {{ __('conciliation.sessions.execute.failed', ['message' => $session->last_failure]) }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                @foreach ($importSlots as $slot)
                    @include('livewire.sessions.partials.slot-card', [
                        'slot' => $slot,
                        'file' => $session->activeFiles->first(fn ($active) => $active->slot === $slot),
                        'attempt' => $this->attempts->get($slot->value),
                        'slotMessage' => $slotMessages[$slot->value] ?? null,
                        'isOpen' => $isOpen,
                    ])
                @endforeach
            </div>
        </div>
    </div>

    @if ($isOpen)
        <x-confirmation-modal wire:model.live="confirmingExecution">
            <x-slot name="title">{{ __('conciliation.sessions.execute.confirm_heading') }}</x-slot>
            <x-slot name="content">{{ __('conciliation.sessions.execute.confirm_body') }}</x-slot>
            <x-slot name="footer">
                <div class="flex gap-3">
                    <x-secondary-button type="button" wire:click="$set('confirmingExecution', false)">{{ __('conciliation.sessions.cancel') }}</x-secondary-button>
                    <x-button type="button" wire:click="execute" wire:loading.attr="disabled">{{ __('conciliation.sessions.execute.action') }}</x-button>
                </div>
            </x-slot>
        </x-confirmation-modal>
    @endif

    @if ($session->status === SessionStatus::Processed)
        <x-confirmation-modal wire:model.live="confirmingReopen">
            <x-slot name="title">{{ __('conciliation.sessions.reopen.confirm_heading') }}</x-slot>
            <x-slot name="content">{{ __('conciliation.sessions.reopen.confirm_body') }}</x-slot>
            <x-slot name="footer">
                <div class="flex gap-3">
                    <x-secondary-button type="button" wire:click="$set('confirmingReopen', false)">{{ __('conciliation.sessions.cancel') }}</x-secondary-button>
                    <x-button type="button" wire:click="reopen" wire:loading.attr="disabled">{{ __('conciliation.sessions.reopen.action') }}</x-button>
                </div>
            </x-slot>
        </x-confirmation-modal>
    @endif

    <x-refusal-modal :message="$refusalMessage" />

    @if ($canDelete)
        <x-confirmation-modal wire:model.live="confirmingDeletion">
            <x-slot name="title">{{ __('conciliation.sessions.delete.confirm_heading') }}</x-slot>
            <x-slot name="content">{{ __('conciliation.sessions.delete.confirm_body') }}</x-slot>
            <x-slot name="footer">
                <div class="flex gap-3">
                    <x-secondary-button type="button" wire:click="$set('confirmingDeletion', false)">{{ __('conciliation.sessions.cancel') }}</x-secondary-button>
                    <x-danger-button type="button" wire:click="delete" wire:loading.attr="disabled">{{ __('conciliation.sessions.delete.action') }}</x-danger-button>
                </div>
            </x-slot>
        </x-confirmation-modal>
    @endif
</div>
