<div class="flex flex-col gap-4">
    <div role="group" aria-label="{{ __('conciliation.reconciliation.pending.filter_label') }}" class="flex flex-wrap gap-2 px-4 sm:px-0">
        @foreach ($classifications as $name)
            <button type="button" wire:click="filterBy('{{ $name }}')" aria-pressed="{{ $classification === $name ? 'true' : 'false' }}"
                @class([
                    'rounded-full px-3 py-1 text-xs font-semibold focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500',
                    'bg-indigo-700 text-white' => $classification === $name,
                    'bg-white text-gray-800 hover:bg-gray-100 border border-gray-300' => $classification !== $name,
                ])>
                {{ __('conciliation.reconciliation.pending.'.$name) }}
            </button>
        @endforeach
    </div>

    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />

    <x-refusal-modal :message="$refusalMessage" />
</div>
