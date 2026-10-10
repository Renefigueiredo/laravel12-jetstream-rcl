<?php

namespace App\Services\Reconciliation\Matching;

use App\Enums\DifferenceTreatment;
use App\Enums\StatementLineKind;

final class AuthorizationStatement
{
    /**
     * Every movement of an authorization, oldest payment first, with what was left after each.
     *
     * The statement is read from the links and never stored: its last balance is the balance
     * of the authorization.
     *
     * @param  list<StatementEntry>  $links  In any order
     * @return list<StatementLine>
     */
    public function lines(int $authorizedCents, array $links): array
    {
        usort($links, fn (StatementEntry $a, StatementEntry $b): int => [$a->paidOn, $a->paymentId] <=> [$b->paidOn, $b->paymentId]);

        $lines = [];
        $remaining = $authorizedCents;

        foreach ($links as $link) {
            $remaining -= $link->paymentCents;
            $lines[] = $this->line(StatementLineKind::Payment, $link, $link->paymentCents, $link->paymentCents, $remaining);

            if ($link->discountCents > 0) {
                $remaining -= $link->discountCents;
                $lines[] = $this->line(StatementLineKind::Discount, $link, $link->discountCents, $link->discountCents, $remaining);
            }

            if ($link->writeoffCents > 0) {
                $remaining -= $link->writeoffCents;
                $lines[] = $this->line(StatementLineKind::ToleranceWriteoff, $link, $link->writeoffCents, $link->writeoffCents, $remaining);
            }

            if ($link->excessCents > 0 && $link->treatment !== null) {
                $kind = $link->treatment === DifferenceTreatment::AcceptedSurcharge
                    ? StatementLineKind::AcceptedSurcharge
                    : StatementLineKind::Overpayment;

                $lines[] = $this->line($kind, $link, 0, $link->excessCents, $remaining);
            }
        }

        return $lines;
    }

    /**
     * What is left to pay after every line; the same as the last line, or the whole amount without lines.
     *
     * @param  list<StatementLine>  $lines
     */
    public function balance(int $authorizedCents, array $lines): int
    {
        return $lines === [] ? $authorizedCents : $lines[array_key_last($lines)]->balanceAfterCents;
    }

    private function line(StatementLineKind $kind, StatementEntry $link, int $amountCents, int $informedCents, int $remaining): StatementLine
    {
        return new StatementLine(
            kind: $kind,
            linkId: $link->linkId,
            paymentId: $link->paymentId,
            paidOn: $link->paidOn,
            amountCents: $amountCents,
            informedCents: $informedCents,
            balanceAfterCents: max(0, $remaining),
        );
    }
}
