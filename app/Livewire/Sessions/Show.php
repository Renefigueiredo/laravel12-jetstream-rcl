<?php

namespace App\Livewire\Sessions;

use App\Actions\Conciliation\ActionRefusedException;
use App\Actions\Conciliation\CancelImportAttempt;
use App\Actions\Conciliation\ConfirmPeriodDivergence;
use App\Actions\Conciliation\DeleteSession;
use App\Actions\Conciliation\ExecuteReconciliation;
use App\Actions\Conciliation\ReopenSession;
use App\Actions\Conciliation\SubmitSpreadsheet;
use App\Enums\ImportAttemptStatus;
use App\Enums\ImportSlot;
use App\Enums\SessionStatus;
use App\Models\ImportAttempt;
use App\Models\ReconciliationSession;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $sessionId;

    /**
     * Files being uploaded, keyed by slot.
     *
     * @var array<string, mixed>
     */
    public array $uploads = [];

    /**
     * Message shown on a card after the last action on it, keyed by slot.
     *
     * @var array<string, array{style: string, text: string}>
     */
    public array $slotMessages = [];

    public bool $confirmingExecution = false;

    public bool $confirmingReopen = false;

    public bool $confirmingDeletion = false;

    public bool $showingRefusal = false;

    public string $refusalMessage = '';

    public function mount(ReconciliationSession $session, CancelImportAttempt $cancelImportAttempt): void
    {
        $this->authorize('view', $session);

        $this->sessionId = $session->id;

        $session->importAttempts()
            ->where('status', ImportAttemptStatus::AwaitingConfirmation)
            ->where('user_id', auth()->id())
            ->get()
            ->each(fn (ImportAttempt $attempt): bool => $cancelImportAttempt->handle($attempt));
    }

    #[Computed]
    public function session(): ?ReconciliationSession
    {
        return ReconciliationSession::query()
            ->with(['activeFiles.uploader', 'creator'])
            ->find($this->sessionId);
    }

    /**
     * The latest unfinished or refused upload of each slot.
     *
     * @return Collection<string, ImportAttempt>
     */
    #[Computed]
    public function attempts(): Collection
    {
        return ImportAttempt::query()
            ->where('reconciliation_session_id', $this->sessionId)
            ->whereNot('status', ImportAttemptStatus::Cancelled)
            ->orderByDesc('id')
            ->get()
            ->unique(fn (ImportAttempt $attempt): string => $attempt->slot->value)
            ->reject(fn (ImportAttempt $attempt): bool => $attempt->status === ImportAttemptStatus::Accepted)
            ->keyBy(fn (ImportAttempt $attempt): string => $attempt->slot->value);
    }

    /**
     * Whether the page must keep polling for the progress of queued work.
     */
    #[Computed]
    public function isWorking(): bool
    {
        return $this->session?->status === SessionStatus::Processing
            || $this->attempts->contains(fn (ImportAttempt $attempt): bool => $attempt->status->isInProgress());
    }

    public function updatedUploads(mixed $file, string $slot): void
    {
        $this->submit($slot);
    }

    public function submit(string $slot, ?SubmitSpreadsheet $submitSpreadsheet = null): void
    {
        $importSlot = ImportSlot::tryFrom($slot);
        $file = $this->uploads[$slot] ?? null;
        $session = $this->sessionOrRedirect();

        if ($importSlot === null || $file === null || $session === null) {
            return;
        }

        unset($this->slotMessages[$slot]);

        try {
            $attempt = ($submitSpreadsheet ?? app(SubmitSpreadsheet::class))->handle(auth()->user(), $session, $importSlot, $file);

            if ($attempt === null) {
                $this->slotMessages[$slot] = ['style' => 'info', 'text' => __('conciliation.import.already_loaded')];
            }
        } catch (ActionRefusedException $exception) {
            $this->slotMessages[$slot] = ['style' => 'danger', 'text' => $exception->getMessage()];
        } finally {
            unset($this->uploads[$slot]);
            $this->refreshState();
        }
    }

    public function confirmDivergence(int $attemptId, ConfirmPeriodDivergence $confirmPeriodDivergence): void
    {
        $attempt = $this->attemptOfSession($attemptId);

        if ($attempt === null) {
            return;
        }

        try {
            $confirmPeriodDivergence->handle(auth()->user(), $attempt);
        } catch (ActionRefusedException $exception) {
            $this->slotMessages[$attempt->slot->value] = ['style' => 'danger', 'text' => $exception->getMessage()];
        }

        $this->refreshState();
    }

    public function cancelAttempt(int $attemptId, CancelImportAttempt $cancelImportAttempt): void
    {
        $attempt = $this->attemptOfSession($attemptId);

        if ($attempt === null) {
            return;
        }

        $this->authorize('upload', $attempt->session);

        if ($cancelImportAttempt->handle($attempt)) {
            $this->slotMessages[$attempt->slot->value] = ['style' => 'info', 'text' => __('conciliation.import.divergence.cancelled')];
        }

        $this->refreshState();
    }

    public function execute(ExecuteReconciliation $executeReconciliation): void
    {
        $this->confirmingExecution = false;

        $this->runSessionAction(
            fn (ReconciliationSession $session) => $executeReconciliation->handle(auth()->user(), $session),
            __('conciliation.sessions.execute.requested'),
        );
    }

    public function reopen(ReopenSession $reopenSession): void
    {
        $this->confirmingReopen = false;

        $this->runSessionAction(
            fn (ReconciliationSession $session) => $reopenSession->handle(auth()->user(), $session),
            __('conciliation.sessions.reopen.done'),
        );
    }

    public function delete(DeleteSession $deleteSession): void
    {
        $this->confirmingDeletion = false;

        $session = $this->sessionOrRedirect();

        if ($session === null) {
            return;
        }

        try {
            $deleteSession->handle(auth()->user(), $session);
        } catch (ActionRefusedException $exception) {
            $this->showRefusal($exception->getMessage());
            $this->refreshState();

            return;
        }

        session()->flash('flash.banner', __('conciliation.sessions.delete.done'));

        $this->redirectRoute('sessions.index');
    }

    public function render(): View
    {
        if ($this->session === null) {
            $this->redirectToMissingSession();

            return view('livewire.sessions.missing');
        }

        return view('livewire.sessions.show', [
            'session' => $this->session,
            'importSlots' => ImportSlot::cases(),
            'engineEnabled' => (bool) config('conciliation.engine_enabled'),
            'timezone' => config('conciliation.display_timezone'),
        ]);
    }

    /**
     * @param  callable(ReconciliationSession): void  $action
     */
    protected function runSessionAction(callable $action, string $successMessage): void
    {
        $session = $this->sessionOrRedirect();

        if ($session === null) {
            return;
        }

        try {
            $action($session);

            $this->dispatch('banner-message', style: 'success', message: $successMessage);
        } catch (ActionRefusedException $exception) {
            $this->showRefusal($exception->getMessage());
        }

        $this->refreshState();
    }

    protected function showRefusal(string $message): void
    {
        $this->refusalMessage = $message;
        $this->showingRefusal = true;
    }

    protected function attemptOfSession(int $attemptId): ?ImportAttempt
    {
        if ($this->sessionOrRedirect() === null) {
            return null;
        }

        return ImportAttempt::query()
            ->where('reconciliation_session_id', $this->sessionId)
            ->find($attemptId);
    }

    /**
     * The session may have been deleted in another tab since the page was loaded.
     */
    protected function sessionOrRedirect(): ?ReconciliationSession
    {
        $this->refreshState();

        if ($this->session === null) {
            $this->redirectToMissingSession();
        }

        return $this->session;
    }

    protected function redirectToMissingSession(): void
    {
        session()->flash('flash.banner', __('conciliation.sessions.not_found'));
        session()->flash('flash.bannerStyle', 'danger');

        $this->redirectRoute('sessions.index');
    }

    protected function refreshState(): void
    {
        unset($this->session, $this->attempts, $this->isWorking);
    }
}
