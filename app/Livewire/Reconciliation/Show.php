<?php

namespace App\Livewire\Reconciliation;

use App\Models\ReconciliationRun;
use App\Models\ReconciliationSession;
use App\Services\Reconciliation\RunTotals;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    /**
     * @var list<string>
     */
    public const TABS = ['pendencias', 'investigacao', 'conciliados', 'antes', 'cartoes', 'excluidos'];

    #[Locked]
    public int $sessionId;

    #[Url(as: 'aba')]
    public string $tab = 'pendencias';

    public function mount(ReconciliationSession $session): void
    {
        $this->authorize('view', $session);

        $this->sessionId = $session->id;

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'pendencias';
        }
    }

    #[Computed]
    public function session(): ReconciliationSession
    {
        return ReconciliationSession::query()->with('currentRun.requester')->findOrFail($this->sessionId);
    }

    #[Computed]
    public function run(): ?ReconciliationRun
    {
        return $this->session->currentRun;
    }

    /**
     * Totals as they are now, after every decision taken on the screen.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function totals(): ?array
    {
        return $this->run === null ? null : app(RunTotals::class)->for($this->run);
    }

    public function showTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->tab = $tab;
        }
    }

    #[On('reconciliation-changed')]
    public function refreshTotals(): void
    {
        unset($this->totals, $this->run, $this->session);
    }

    public function render(): View
    {
        return view('livewire.reconciliation.show', [
            'timezone' => config('conciliation.display_timezone'),
        ]);
    }
}
