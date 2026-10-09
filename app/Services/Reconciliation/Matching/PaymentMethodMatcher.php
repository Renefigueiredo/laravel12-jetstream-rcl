<?php

namespace App\Services\Reconciliation\Matching;

final class PaymentMethodMatcher
{
    /**
     * Both sides name a card and the cards differ.
     */
    public function cardsDiffer(AuthorizationCandidate $authorization, PaymentCandidate $payment): bool
    {
        return $authorization->card !== null && $payment->card !== null && $authorization->card !== $payment->card;
    }

    /**
     * Among payments tied for an authorization, the single one that fits it; null when the tie stands.
     *
     * @param  list<PaymentCandidate>  $payments
     */
    public function preferredPayment(AuthorizationCandidate $authorization, array $payments): ?PaymentCandidate
    {
        if ($authorization->card !== null) {
            $sameCard = $this->only($payments, fn (PaymentCandidate $payment): bool => $payment->card === $authorization->card);

            if ($sameCard !== null) {
                return $sameCard;
            }
        }

        return $this->only($payments, fn (PaymentCandidate $payment): bool => ($payment->card !== null) === $authorization->paysByCard);
    }

    /**
     * Among authorizations tied for a payment, the single one that fits it; null when the tie stands.
     *
     * @param  list<AuthorizationCandidate>  $authorizations
     */
    public function preferredAuthorization(PaymentCandidate $payment, array $authorizations): ?AuthorizationCandidate
    {
        if ($payment->card !== null) {
            $sameCard = $this->only($authorizations, fn (AuthorizationCandidate $authorization): bool => $authorization->card === $payment->card);

            if ($sameCard !== null) {
                return $sameCard;
            }
        }

        return $this->only($authorizations, fn (AuthorizationCandidate $authorization): bool => $authorization->paysByCard === ($payment->card !== null));
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @param  callable(T): bool  $fits
     * @return T|null
     */
    private function only(array $items, callable $fits): mixed
    {
        $fitting = array_values(array_filter($items, $fits));

        return count($fitting) === 1 ? $fitting[0] : null;
    }
}
