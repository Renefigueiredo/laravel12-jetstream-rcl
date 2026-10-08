<?php

namespace App\Policies;

use App\Models\ReconciliationSession;
use App\Models\User;

class ReconciliationSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->worksWithSessions($user);
    }

    public function view(User $user, ReconciliationSession $session): bool
    {
        return $this->worksWithSessions($user);
    }

    public function create(User $user): bool
    {
        return $this->worksWithSessions($user);
    }

    public function upload(User $user, ReconciliationSession $session): bool
    {
        return $this->worksWithSessions($user);
    }

    public function execute(User $user, ReconciliationSession $session): bool
    {
        return $this->worksWithSessions($user);
    }

    public function reopen(User $user, ReconciliationSession $session): bool
    {
        return $this->worksWithSessions($user);
    }

    public function delete(User $user, ReconciliationSession $session): bool
    {
        return $this->worksWithSessions($user);
    }

    /**
     * Every role works on every session and on both operating units.
     */
    protected function worksWithSessions(User $user): bool
    {
        return $user->role !== null;
    }
}
