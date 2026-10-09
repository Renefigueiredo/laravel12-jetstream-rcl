<?php

namespace App\Livewire\Concerns;

/**
 * A refused action is told in a dialog the user has to close, so it is not missed.
 * The view of the component must include <x-refusal-modal>.
 */
trait ShowsRefusal
{
    public bool $showingRefusal = false;

    public string $refusalMessage = '';

    protected function showRefusal(string $message): void
    {
        $this->refusalMessage = $message;
        $this->showingRefusal = true;
    }
}
