@php
    use App\Enums\DifferenceTreatment;
    use App\Enums\LinkOrigin;
    use App\Enums\StatementLineKind;
    use App\Enums\InstallmentStatus;
    use App\Support\Money;

    $authorization = $getRecord();
    $statement = $getLivewire()->statementFor($authorization);
    $rows = $statement['rows'];
    $installments = $statement['installments'];
    $positions = $statement['positions'];
    $pending = array_filter($installments, fn ($installment) => $installment->status !== InstallmentStatus::Paid);
    $timezone = config('conciliation.display_timezone');
@endphp

<div class="flex flex-col gap-3 text-sm">
    <h4 class="font-semibold text-gray-900">{{ __('conciliation.dashboard.statement.heading') }}</h4>

    @if ($rows === [])
        <p class="text-gray-700">{{ __('conciliation.dashboard.statement.empty') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead>
                    <tr class="text-gray-600">
                        <th scope="col" class="py-1 pe-4 font-medium">{{ __('conciliation.dashboard.statement.date') }}</th>
                        <th scope="col" class="py-1 pe-4 font-medium">{{ __('conciliation.dashboard.statement.movement') }}</th>
                        <th scope="col" class="py-1 pe-4 font-medium">{{ __('conciliation.dashboard.statement.how') }}</th>
                        <th scope="col" class="py-1 pe-4 text-end font-medium">{{ __('conciliation.dashboard.statement.amount') }}</th>
                        <th scope="col" class="py-1 pe-4 text-end font-medium">{{ __('conciliation.dashboard.statement.balance_after') }}</th>
                        <th scope="col" class="py-1"><span class="sr-only">{{ __('conciliation.dashboard.statement.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $line = $row['line'];
                            $link = $row['link'];
                            $payment = $link->payment;
                            $isPayment = $line->kind === StatementLineKind::Payment;
                            $how = match (true) {
                                $authorization->isCreatedInReconciliation() => __('conciliation.reconciliation.link_origin.created'),
                                $link->is_installment => __('conciliation.reconciliation.link_origin.installment'),
                                $link->origin === LinkOrigin::Manual => __('conciliation.reconciliation.link_origin.manual'),
                                default => __('conciliation.reconciliation.link_origin.automatic'),
                            };
                        @endphp
                        <tr wire:key="statement-{{ $authorization->id }}-{{ $line->linkId }}-{{ $line->kind->value }}" class="border-t border-gray-200 align-top">
                            <td class="py-2 pe-4 whitespace-nowrap">{{ $isPayment ? $payment->paid_on->format('d/m/Y') : '' }}</td>
                            <td class="py-2 pe-4">
                                @if ($isPayment)
                                    <button type="button" wire:click="mountAction('paymentOfLink', { payment: {{ $payment->id }} })"
                                        class="text-start font-semibold text-indigo-800 underline focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md">
                                        {{ $payment->supplier_name }}
                                    </button>
                                    <p class="text-gray-700">{{ $payment->unit->label() }} · {{ $payment->session->label() }}</p>
                                @else
                                    <p @class(['font-semibold', 'text-red-800' => $line->kind === StatementLineKind::Overpayment, 'text-gray-900' => $line->kind !== StatementLineKind::Overpayment])>{{ $line->kind->label() }}</p>
                                    @if ($link->justification && $line->kind !== StatementLineKind::ToleranceWriteoff)
                                        <p class="text-gray-700">{{ $link->justification_category?->label() }}: {{ $link->justification }}</p>
                                    @endif
                                @endif
                            </td>
                            <td class="py-2 pe-4">
                                @if ($isPayment)
                                    <p>{{ $how }}</p>
                                    @if (isset($positions[$payment->id]))
                                        <p class="text-gray-700">{{ __('conciliation.dashboard.forecast.installment', ['position' => $positions[$payment->id]]) }}</p>
                                    @elseif ($installments !== [])
                                        <p class="text-amber-800">{{ __('conciliation.dashboard.forecast.out_of_plan') }}</p>
                                    @endif
                                    @if ($link->decider)
                                        <p class="text-gray-700">{{ $link->decider->name }}, {{ $link->decided_at?->timezone($timezone)->format('d/m/Y H:i') }}</p>
                                    @endif
                                @endif
                            </td>
                            <td class="py-2 pe-4 text-end whitespace-nowrap">{{ Money::format($line->informedCents) }}</td>
                            <td class="py-2 pe-4 text-end whitespace-nowrap font-semibold">{{ Money::format($line->balanceAfterCents) }}</td>
                            <td class="py-2 text-end">
                                @if ($isPayment)
                                    <button type="button" wire:click="mountAction('undoLink', { link: {{ $link->id }} })"
                                        class="rounded-md text-red-800 underline focus:outline-2 focus:outline-offset-2 focus:outline-red-500">
                                        {{ __('conciliation.dashboard.statement.undo') }}
                                        <span class="sr-only">{{ $payment->supplier_name }}, {{ Money::format($payment->amount_cents) }}</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($pending !== [] && $authorization->balanceCents() > 0)
        <div class="flex flex-col gap-1">
            <h4 class="font-semibold text-gray-900">{{ __('conciliation.dashboard.forecast.heading') }}</h4>
            <p class="text-gray-600">{{ __('conciliation.dashboard.forecast.'.($statement['fromPlan'] ? 'from_plan' : 'from_condition')) }}</p>
            <ul class="flex flex-col gap-1">
                @foreach ($pending as $installment)
                    <li wire:key="installment-{{ $authorization->id }}-{{ $installment->position }}" class="flex flex-wrap items-center gap-x-3">
                        <span>{{ __('conciliation.dashboard.forecast.installment', ['position' => $installment->position]) }}</span>
                        <span class="font-semibold">{{ Money::format($installment->amountCents) }}</span>
                        @if ($installment->expectedMonth)
                            <span class="text-gray-700">{{ __('conciliation.dashboard.forecast.expected_in', ['month' => \Illuminate\Support\Carbon::parse($installment->expectedMonth)->format('m/Y')]) }}</span>
                        @endif
                        <span @class(['rounded-md px-2 py-0.5 text-xs font-semibold', 'bg-red-100 text-red-900' => $installment->status === InstallmentStatus::Overdue, 'bg-gray-100 text-gray-800' => $installment->status !== InstallmentStatus::Overdue])>{{ $installment->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
