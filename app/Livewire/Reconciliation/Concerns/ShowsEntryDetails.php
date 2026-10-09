<?php

namespace App\Livewire\Reconciliation\Concerns;

use App\Models\AuthorizationEntry;
use App\Models\PaymentEntry;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

trait ShowsEntryDetails
{
    /**
     * Open everything known about the authorization of a row, as it came in the spreadsheet.
     */
    protected function authorizationDetailsAction(string $name = 'authorizationDetails'): Action
    {
        return Action::make($name)
            ->label(__('conciliation.reconciliation.details.view_authorization'))
            ->icon('heroicon-o-document-text')
            ->iconButton()
            ->tooltip(__('conciliation.reconciliation.details.view_authorization'))
            ->visible(fn (Model $record): bool => $this->authorizationIdOf($record) !== null)
            ->modalHeading(__('conciliation.reconciliation.details.authorization_heading'))
            ->modalContent(fn (Model $record): View => view('livewire.reconciliation.partials.authorization-details', [
                'authorization' => AuthorizationEntry::query()
                    ->with(['state', 'session', 'links.payment', 'creator'])
                    ->findOrFail($this->authorizationIdOf($record)),
                'timezone' => config('conciliation.display_timezone'),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('conciliation.reconciliation.details.close'));
    }

    /**
     * Open everything known about the payment of a row, with the other rows of its obligation.
     */
    protected function paymentDetailsAction(string $name = 'paymentDetails'): Action
    {
        return Action::make($name)
            ->label(__('conciliation.reconciliation.details.view_payment'))
            ->icon('heroicon-o-banknotes')
            ->iconButton()
            ->tooltip(__('conciliation.reconciliation.details.view_payment'))
            ->visible(fn (Model $record): bool => $this->paymentIdOf($record) !== null)
            ->modalHeading(__('conciliation.reconciliation.details.payment_heading'))
            ->modalContent(function (Model $record): View {
                $payment = PaymentEntry::query()->with(['session', 'link.authorization'])->findOrFail($this->paymentIdOf($record));

                return view('livewire.reconciliation.partials.payment-details', [
                    'payment' => $payment,
                    'siblings' => PaymentEntry::query()
                        ->where('import_file_id', $payment->import_file_id)
                        ->where('obligation_number', $payment->obligation_number)
                        ->whereKeyNot($payment->id)
                        ->orderBy('row_number')
                        ->get(),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('conciliation.reconciliation.details.close'));
    }

    /**
     * The row may be the authorization itself or anything that points to one.
     */
    protected function authorizationIdOf(Model $record): ?int
    {
        return match (true) {
            $record instanceof AuthorizationEntry => $record->getKey(),
            $record instanceof PaymentEntry => null,
            default => $record->authorization_entry_id,
        };
    }

    /**
     * The row may be the payment itself or anything that points to one.
     */
    protected function paymentIdOf(Model $record): ?int
    {
        return match (true) {
            $record instanceof PaymentEntry => $record->getKey(),
            $record instanceof AuthorizationEntry => null,
            default => $record->payment_entry_id,
        };
    }
}
