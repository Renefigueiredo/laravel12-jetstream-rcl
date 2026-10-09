@php
    use App\Support\Money;

    $statePath = $getStatePath();
    $fieldId = $getId();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            search: '',
            amounts: @js(collect($payments)->mapWithKeys(fn ($payment) => [(string) $payment['id'] => $payment['amount_cents']])->all()),
            balance: {{ (int) $balanceCents }},
            selected() { return ($wire.get('{{ $statePath }}') ?? []).map(String) },
            sum() { return this.selected().reduce((total, id) => total + (this.amounts[id] ?? 0), 0) },
            money(cents) { return (cents < 0 ? '-' : '') + 'R$ ' + (Math.abs(cents) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
            matches(text) { return this.search.trim() === '' || text.includes(this.search.trim().toUpperCase()) },
        }"
        class="flex flex-col gap-3"
    >
        <dl role="status" class="grid grid-cols-2 gap-x-4 gap-y-1 rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm sm:grid-cols-4">
            <div class="flex flex-col">
                <dt class="text-gray-600">{{ __('conciliation.reconciliation.picker.selected') }}</dt>
                <dd class="font-semibold text-gray-900" x-text="selected().length"></dd>
            </div>
            <div class="flex flex-col">
                <dt class="text-gray-600">{{ __('conciliation.reconciliation.picker.total') }}</dt>
                <dd class="font-semibold text-gray-900" x-text="money(sum())"></dd>
            </div>
            <div class="flex flex-col">
                <dt class="text-gray-600">{{ __('conciliation.reconciliation.picker.balance') }}</dt>
                <dd class="font-semibold text-gray-900">{{ Money::format($balanceCents) }}</dd>
            </div>
            <div class="flex flex-col">
                <dt class="text-gray-600">{{ __('conciliation.reconciliation.picker.difference') }}</dt>
                <dd class="font-semibold" :class="sum() === balance ? 'text-green-800' : 'text-gray-900'" x-text="money(sum() - balance)"></dd>
            </div>
        </dl>

        <div>
            <input id="{{ $fieldId }}-search" type="search" x-model="search" autocomplete="off"
                placeholder="{{ __('conciliation.reconciliation.picker.search') }}"
                aria-label="{{ __('conciliation.reconciliation.picker.search') }}"
                class="block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500" />
        </div>

        <div class="overflow-auto rounded-md border border-gray-200" style="max-height: 22vh; min-height: 9rem;" tabindex="0">
            <table class="min-w-full text-left text-sm">
                <thead class="sticky top-0 bg-gray-50">
                    <tr>
                        <th scope="col" class="w-10 px-3 py-1.5"><span class="sr-only">{{ __('conciliation.reconciliation.picker.select') }}</span></th>
                        <th scope="col" class="px-3 py-1.5">{{ __('conciliation.reconciliation.details.supplier') }}</th>
                        <th scope="col" class="px-3 py-1.5">{{ __('conciliation.reconciliation.columns.paid_on') }}</th>
                        <th scope="col" class="px-3 py-1.5">{{ __('conciliation.reconciliation.columns.card') }}</th>
                        <th scope="col" class="px-3 py-1.5 text-end">{{ __('conciliation.reconciliation.picker.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr wire:key="{{ $fieldId }}-payment-{{ $payment['id'] }}" x-show="matches(@js(mb_strtoupper($payment['supplier'])))" class="border-t border-gray-200">
                            <td class="px-3 py-1.5">
                                <input id="{{ $fieldId }}-payment-{{ $payment['id'] }}" type="checkbox" value="{{ $payment['id'] }}" wire:model.live="{{ $statePath }}"
                                    class="rounded-sm border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            </td>
                            <td class="px-3 py-1.5">
                                <label for="{{ $fieldId }}-payment-{{ $payment['id'] }}" class="cursor-pointer">{{ $payment['supplier'] }}</label>
                            </td>
                            <td class="px-3 py-1.5" style="white-space: nowrap;">{{ $payment['paid_on'] }}</td>
                            <td class="px-3 py-1.5">{{ $payment['card'] }}</td>
                            <td class="px-3 py-1.5 text-end font-medium" style="white-space: nowrap;">{{ Money::format($payment['amount_cents']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-gray-700">{{ __('conciliation.reconciliation.picker.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-dynamic-component>
