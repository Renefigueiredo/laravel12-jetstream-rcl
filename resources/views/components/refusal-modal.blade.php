@props(['message'])

<x-confirmation-modal wire:model.live="showingRefusal">
    <x-slot name="title">{{ __('conciliation.sessions.refused_heading') }}</x-slot>
    <x-slot name="content"><span role="alert">{{ $message }}</span></x-slot>
    <x-slot name="footer">
        <x-secondary-button type="button" wire:click="$set('showingRefusal', false)">{{ __('conciliation.sessions.close') }}</x-secondary-button>
    </x-slot>
</x-confirmation-modal>
