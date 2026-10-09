@php
    use App\Support\Money;
@endphp

<div class="flex flex-col gap-4 text-sm text-gray-900">
    <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
        @foreach ([
            __('conciliation.reconciliation.details.supplier') => $payment->supplier_name,
            __('conciliation.reconciliation.columns.paid') => Money::format($payment->amount_cents),
            __('conciliation.reconciliation.details.obligation_amount') => Money::format($payment->obligation_amount_cents),
            __('conciliation.reconciliation.columns.paid_on') => $payment->paid_on->format('d/m/Y'),
            __('conciliation.reconciliation.details.unit') => $payment->unit->label(),
            __('conciliation.reconciliation.details.operation') => trim($payment->operation_code.' '.$payment->operation_name),
            __('conciliation.reconciliation.details.species') => $payment->species,
            __('conciliation.reconciliation.columns.card') => $payment->card,
            __('conciliation.reconciliation.details.obligation') => $payment->obligation_number,
            __('conciliation.reconciliation.details.source_document') => $payment->source_document,
            __('conciliation.reconciliation.details.session') => $payment->session->label(),
            __('conciliation.reconciliation.details.origin') => __('conciliation.reconciliation.details.spreadsheet_row', ['row' => $payment->row_number]),
        ] as $label => $value)
            @if (filled($value))
                <div class="flex flex-col">
                    <dt class="text-gray-600">{{ $label }}</dt>
                    <dd class="font-medium break-words">{{ $value }}</dd>
                </div>
            @endif
        @endforeach
    </dl>

    @if ($payment->link)
        <p class="rounded-md border border-green-300 bg-green-50 p-3 text-green-900">
            {{ __('conciliation.reconciliation.details.linked_to', [
                'supplier' => $payment->link->authorization->supplier_name,
                'amount' => Money::format($payment->link->authorization->amount_cents),
            ]) }}
        </p>
    @endif

    @if ($siblings->isNotEmpty())
        <section class="flex flex-col gap-2">
            <h4 class="font-semibold">{{ __('conciliation.reconciliation.details.obligation_rows') }}</h4>
            <ul class="flex flex-col gap-1">
                @foreach ($siblings as $sibling)
                    <li class="border-t border-gray-200 pt-1">
                        {{ $sibling->operation_code }} {{ $sibling->operation_name }} · {{ Money::format($sibling->amount_cents) }} · {{ $sibling->paid_on->format('d/m/Y') }}
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($payment->raw)
        <section class="flex flex-col gap-2">
            <h4 class="font-semibold">{{ __('conciliation.reconciliation.details.spreadsheet_data') }}</h4>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                @foreach ($payment->raw as $column => $value)
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
