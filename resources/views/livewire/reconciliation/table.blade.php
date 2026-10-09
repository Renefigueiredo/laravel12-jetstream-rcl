<div>
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />

    @isset($refusalMessage)
        <x-refusal-modal :message="$refusalMessage" />
    @endisset
</div>
