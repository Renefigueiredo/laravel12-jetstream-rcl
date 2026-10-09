<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('conciliation.settings.title') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 flex flex-col gap-6">
            <form wire:submit="save" class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-col gap-6">
                <p class="text-sm text-gray-700">{{ __('conciliation.settings.intro') }}</p>

                <div class="grid gap-6 sm:grid-cols-2">
                    @foreach ([
                        'toleranceAmount' => 'tolerance_amount',
                        'tolerancePercent' => 'tolerance_percent',
                        'toleranceCap' => 'tolerance_cap',
                        'surchargeCapPercent' => 'surcharge_cap',
                    ] as $field => $key)
                        <div class="flex flex-col gap-1">
                            <x-label for="settings-{{ $key }}" value="{{ __('conciliation.settings.fields.'.$key) }}" />
                            <x-input id="settings-{{ $key }}" type="text" inputmode="decimal" autocomplete="off" class="block w-full"
                                wire:model="form.{{ $field }}" aria-describedby="settings-{{ $key }}-help" />
                            <p id="settings-{{ $key }}-help" class="text-sm text-gray-600">{{ __('conciliation.settings.help.'.$key) }}</p>
                            <x-input-error for="form.{{ $field }}" />
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm text-gray-600">
                        @if ($this->settings->updater)
                            {{ __('conciliation.settings.last_change', [
                                'name' => $this->settings->updater->name,
                                'when' => $this->settings->updated_at->timezone($timezone)->format('d/m/Y H:i'),
                            ]) }}
                        @else
                            {{ __('conciliation.settings.never_changed') }}
                        @endif
                    </p>

                    <x-button type="submit" wire:loading.attr="disabled">{{ __('conciliation.settings.save') }}</x-button>
                </div>
            </form>
        </div>
    </div>

    <x-refusal-modal :message="$refusalMessage" />
</div>
