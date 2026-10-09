<?php

namespace App\Livewire\Reconciliation;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\UpdateReconciliationSettings;
use App\Livewire\Concerns\ShowsRefusal;
use App\Livewire\Forms\ReconciliationSettingsForm;
use App\Models\ReconciliationSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Settings extends Component
{
    use ShowsRefusal;

    public ReconciliationSettingsForm $form;

    public function mount(): void
    {
        $this->authorize('configure-tolerance');

        $this->form->fillFrom($this->settings);
    }

    #[Computed]
    public function settings(): ReconciliationSettings
    {
        return ReconciliationSettings::current()->load('updater');
    }

    public function save(UpdateReconciliationSettings $updateReconciliationSettings): void
    {
        $this->authorize('configure-tolerance');

        $this->form->normalize();
        $this->form->validate();

        try {
            $updateReconciliationSettings->handle(
                auth()->user(),
                $this->form->toleranceCents(),
                $this->form->toleranceBasisPoints(),
                $this->form->toleranceCapCents(),
                $this->form->surchargeCapBasisPoints(),
            );
        } catch (ActionRefusedException $exception) {
            $this->showRefusal($exception->getMessage());

            return;
        }

        unset($this->settings);

        $this->form->fillFrom($this->settings);
        $this->dispatch('banner-message', style: 'success', message: __('conciliation.settings.saved'));
    }

    public function render(): View
    {
        return view('livewire.reconciliation.settings', [
            'timezone' => config('conciliation.display_timezone'),
        ]);
    }
}
