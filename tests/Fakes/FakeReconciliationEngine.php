<?php

namespace Tests\Fakes;

use App\Contracts\ReconciliationEngine;
use App\Models\ReconciliationSession;
use RuntimeException;

class FakeReconciliationEngine implements ReconciliationEngine
{
    /**
     * Calls received, in order: "discard:{id}" and "run:{id}".
     *
     * @var list<string>
     */
    public array $calls = [];

    public bool $shouldFail = false;

    /**
     * @var list<int>
     */
    public array $progressSteps = [25, 60];

    public function run(ReconciliationSession $session, callable $reportProgress): void
    {
        $this->calls[] = 'run:'.$session->id;

        foreach ($this->progressSteps as $percent) {
            $reportProgress($percent);
        }

        if ($this->shouldFail) {
            throw new RuntimeException('Falha simulada do motor.');
        }
    }

    public function discardResult(ReconciliationSession $session): void
    {
        $this->calls[] = 'discard:'.$session->id;
    }
}
