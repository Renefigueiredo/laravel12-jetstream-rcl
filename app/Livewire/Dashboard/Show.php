<?php

namespace App\Livewire\Dashboard;

use App\Services\Reconciliation\AuthorizationPanel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    /**
     * @var list<string>
     */
    public const TABS = ['abertas', 'conciliadas', 'divergencias'];

    #[Url(as: 'aba')]
    public string $tab = 'abertas';

    public function mount(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'abertas';
        }
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function totals(): array
    {
        return app(AuthorizationPanel::class)->totals();
    }

    /**
     * @return array{overdue: int, overpaid: int, divergences: int}
     */
    #[Computed]
    public function alerts(): array
    {
        return app(AuthorizationPanel::class)->alerts();
    }

    #[Computed]
    public function hasProcessedSession(): bool
    {
        return app(AuthorizationPanel::class)->hasProcessedSession();
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
        unset($this->totals, $this->alerts);
    }

    public function render(): View
    {
        return view('livewire.dashboard.show');
    }
}
