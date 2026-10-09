<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('conciliation.sessions.title') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col gap-6">
            <div class="flex justify-end px-4 sm:px-0">
                <x-button type="button" wire:click="openCreateModal">
                    {{ __('conciliation.sessions.new') }}
                </x-button>
            </div>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                {{ $this->table }}
            </div>
        </div>
    </div>

    <x-dialog-modal wire:model.live="showingCreateModal" maxWidth="md">
        <x-slot name="title">
            {{ __('conciliation.sessions.new') }}
        </x-slot>

        <x-slot name="content">
            <form wire:submit="createSession" id="create-session-form" class="flex flex-col gap-2">
                <x-label for="period" value="{{ __('conciliation.sessions.period') }}" />
                <x-input id="period" type="text" class="block w-full" placeholder="{{ __('conciliation.sessions.period_placeholder') }}" inputmode="numeric"
                    aria-describedby="period-help" wire:model.live.debounce.300ms="period" />
                <p id="period-help" class="text-sm text-gray-600">{{ __('conciliation.sessions.period_help') }}</p>
                <x-input-error for="period" />

                @if ($complementaryWarning)
                    <div role="alert" class="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">
                        <p class="font-semibold">{{ __('conciliation.sessions.complementary.heading') }}</p>
                        <p>{{ $complementaryWarning }}</p>
                    </div>
                @endif
            </form>
        </x-slot>

        <x-slot name="footer">
            <div class="flex gap-3">
                <x-secondary-button type="button" wire:click="$set('showingCreateModal', false)">
                    {{ __('conciliation.sessions.cancel') }}
                </x-secondary-button>

                <x-button type="submit" form="create-session-form" wire:loading.attr="disabled">
                    {{ $complementaryWarning ? __('conciliation.sessions.complementary.confirm') : __('conciliation.sessions.new') }}
                </x-button>
            </div>
        </x-slot>
    </x-dialog-modal>

    <x-filament-actions::modals />

    <x-refusal-modal :message="$refusalMessage" />
</div>
