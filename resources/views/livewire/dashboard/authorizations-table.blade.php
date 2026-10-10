@php
    use App\Support\Money;

    $totals = $this->tabTotals;
@endphp

<div class="flex flex-col gap-4">
    <dl role="status" aria-label="{{ __('conciliation.dashboard.tab_totals.label') }}" class="grid grid-cols-2 gap-4 rounded-md bg-white px-6 py-4 text-sm shadow-xl sm:grid-cols-4 sm:rounded-lg">
        @foreach ([
            'authorizations' => $totals['authorizations'],
            'authorized' => Money::format($totals['authorized_cents']),
            'paid' => Money::format($totals['paid_cents']),
            'balance' => Money::format($totals['balance_cents']),
        ] as $key => $value)
            <div class="flex flex-col">
                <dt class="text-gray-600">{{ __('conciliation.dashboard.tab_totals.'.$key) }}</dt>
                <dd class="font-semibold text-gray-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />

    <x-refusal-modal :message="$refusalMessage" />
</div>
