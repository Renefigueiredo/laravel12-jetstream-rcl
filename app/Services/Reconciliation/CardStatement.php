<?php

namespace App\Services\Reconciliation;

use App\Enums\ImportFileStatus;
use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use Illuminate\Database\Eloquent\Builder;

class CardStatement
{
    /**
     * Active authorizations of the session that name a card, with their state.
     *
     * @return Builder<AuthorizationEntry>
     */
    public function authorizations(int $sessionId): Builder
    {
        return AuthorizationEntry::query()
            ->leftJoin('import_files', 'import_files.id', '=', 'authorization_entries.import_file_id')
            ->leftJoin('authorization_states', 'authorization_states.authorization_entry_id', '=', 'authorization_entries.id')
            ->where('authorization_entries.reconciliation_session_id', $sessionId)
            ->whereNotNull('authorization_entries.card')
            ->where(fn ($query) => $query
                ->whereNull('authorization_entries.import_file_id')
                ->orWhere('import_files.status', ImportFileStatus::Active));
    }

    /**
     * Active payments of the session that came from a card invoice, with link and skip.
     *
     * @return Builder<PaymentEntry>
     */
    public function invoiceLines(int $sessionId): Builder
    {
        return PaymentEntry::query()
            ->join('import_files', 'import_files.id', '=', 'payment_entries.import_file_id')
            ->leftJoin('reconciliation_links', 'reconciliation_links.payment_entry_id', '=', 'payment_entries.id')
            ->leftJoin('reconciliation_skips', 'reconciliation_skips.payment_entry_id', '=', 'payment_entries.id')
            ->where('payment_entries.reconciliation_session_id', $sessionId)
            ->where('import_files.status', ImportFileStatus::Active)
            ->whereNotNull('payment_entries.card');
    }

    /**
     * Authorizations of the card that no invoice line has paid yet.
     *
     * @return Builder<AuthorizationEntry>
     */
    public function authorizationsWithoutInvoice(int $sessionId, string $card): Builder
    {
        return AuthorizationEntry::query()->whereIn('id',
            $this->authorizations($sessionId)
                ->where('authorization_entries.card', $card)
                ->whereNull('authorization_states.authorization_entry_id')
                ->select('authorization_entries.id'),
        );
    }

    /**
     * Invoice lines of the card that entered the comparison and have no authorization.
     *
     * @return Builder<PaymentEntry>
     */
    public function linesWithoutAuthorization(int $sessionId, string $card): Builder
    {
        return PaymentEntry::query()->whereIn('id',
            $this->invoiceLines($sessionId)
                ->where('payment_entries.card', $card)
                ->whereNull('reconciliation_links.id')
                ->whereNull('reconciliation_skips.id')
                ->select('payment_entries.id'),
        );
    }
}
