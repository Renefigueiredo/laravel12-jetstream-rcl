<?php

namespace App\Services\Reconciliation;

use App\Models\AuthorizationEntry;
use App\Models\ReconciliationLink;
use App\Services\Reconciliation\Matching\AuthorizationStatement;
use App\Services\Reconciliation\Matching\StatementEntry;
use App\Services\Reconciliation\Matching\StatementLine;

class AuthorizationStatementReader
{
    public function __construct(protected AuthorizationStatement $statement) {}

    /**
     * The statement of an authorization, read from its links.
     *
     * Load `links.payment.session` and `links.decider` beforehand when reading a list.
     *
     * @return list<array{line: StatementLine, link: ReconciliationLink}>
     */
    public function read(AuthorizationEntry $authorization): array
    {
        $links = $authorization->links->keyBy('id');

        $lines = $this->statement->lines(
            $authorization->amount_cents,
            $links->map(fn (ReconciliationLink $link): StatementEntry => new StatementEntry(
                linkId: $link->id,
                paymentId: $link->payment_entry_id,
                paidOn: $link->payment->paid_on->toDateString(),
                paymentCents: $link->payment->amount_cents,
                discountCents: $link->discount_cents,
                writeoffCents: $link->tolerance_writeoff_cents,
                excessCents: $link->excess_cents,
                treatment: $link->treatment,
            ))->values()->all(),
        );

        return array_map(fn (StatementLine $line): array => ['line' => $line, 'link' => $links[$line->linkId]], $lines);
    }
}
