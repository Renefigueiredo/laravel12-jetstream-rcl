@php
    use App\Support\Money;
@endphp

<div class="flex flex-col gap-4 text-sm text-gray-900">
    <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
        @foreach ([
            __('conciliation.reconciliation.details.supplier') => $authorization->supplier_name,
            __('conciliation.reconciliation.details.request') => $authorization->request,
            __('conciliation.reconciliation.columns.authorized') => Money::format($authorization->amount_cents),
            __('conciliation.reconciliation.details.balance') => Money::format($authorization->balanceCents()).' · '.$authorization->status()->label(),
            __('conciliation.reconciliation.columns.authorized_on') => $authorization->authorized_on->format('d/m/Y'),
            __('conciliation.reconciliation.details.payment_method') => $authorization->payment_method,
            __('conciliation.reconciliation.columns.card') => $authorization->card,
            __('conciliation.reconciliation.details.payment_condition') => $authorization->paymentConditionLabel(),
            __('conciliation.reconciliation.details.session') => $authorization->session->label(),
            __('conciliation.reconciliation.details.origin') => $authorization->isCreatedInReconciliation()
                ? __('conciliation.reconciliation.details.created_in_reconciliation')
                : __('conciliation.reconciliation.details.spreadsheet_row', ['row' => $authorization->row_number]),
        ] as $label => $value)
            @if (filled($value))
                <div class="flex flex-col">
                    <dt class="text-gray-600">{{ $label }}</dt>
                    <dd class="font-medium break-words">{{ $value }}</dd>
                </div>
            @endif
        @endforeach
    </dl>

    @if ($authorization->links->isNotEmpty())
        <section class="flex flex-col gap-2">
            <h4 class="font-semibold">{{ __('conciliation.reconciliation.details.linked_payments') }}</h4>
            <ul class="flex flex-col gap-1">
                @foreach ($authorization->links as $link)
                    <li class="border-t border-gray-200 pt-1">
                        {{ $link->payment->supplier_name }} · {{ Money::format($link->payment->amount_cents) }} · {{ $link->payment->paid_on->format('d/m/Y') }}
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($authorization->raw)
        <section class="flex flex-col gap-2">
            <h4 class="font-semibold">{{ __('conciliation.reconciliation.details.spreadsheet_data') }}</h4>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                @foreach ($authorization->raw as $column => $value)
                    @if (filled($value))
                        <div class="flex flex-col border-t border-gray-200 pt-1">
                            <dt class="text-xs text-gray-600">{{ $column }}</dt>
                            <dd class="break-words">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        </section>
    @endif
</div>
