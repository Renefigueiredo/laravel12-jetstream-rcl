@php
    use App\Support\Money;
@endphp

<div class="flex flex-col gap-6">
    <section aria-labelledby="cards-summary" class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-col gap-3">
        <h3 id="cards-summary" class="text-base font-semibold text-gray-900">{{ __('conciliation.reconciliation.cards.heading') }}</h3>

        @if ($this->summary === [])
            <p class="text-sm text-gray-700">{{ __('conciliation.reconciliation.cards.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="pe-4 py-2">{{ __('conciliation.reconciliation.columns.card') }}</th>
                            <th scope="col" class="pe-4 py-2 text-end">{{ __('conciliation.reconciliation.cards.authorizations') }}</th>
                            <th scope="col" class="pe-4 py-2 text-end">{{ __('conciliation.reconciliation.cards.invoice_lines') }}</th>
                            <th scope="col" class="pe-4 py-2 text-end">{{ __('conciliation.reconciliation.cards.linked') }}</th>
                            <th scope="col" class="pe-4 py-2 text-end">{{ __('conciliation.reconciliation.cards.authorizations_without_invoice') }}</th>
                            <th scope="col" class="pe-4 py-2 text-end">{{ __('conciliation.reconciliation.cards.lines_without_authorization') }}</th>
                            <th scope="col" class="py-2 text-end">{{ __('conciliation.reconciliation.cards.excluded') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->summary as $row)
                            <tr wire:key="card-{{ $row['card'] }}"
                                x-on:click="$wire.choose('{{ $row['card'] }}').then(() => document.getElementById('card-linked')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                                @class(['cursor-pointer border-t border-gray-200 align-top hover:bg-indigo-50', 'bg-indigo-50' => $card === $row['card']])>
                                <th scope="row" class="pe-4 py-2 font-semibold">
                                    <button type="button" aria-pressed="{{ $card === $row['card'] ? 'true' : 'false' }}"
                                        class="underline text-indigo-800 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md">
                                        {{ $row['card'] }}
                                    </button>
                                </th>
                                <td class="pe-4 py-2 text-end">{{ $row['authorizations'] }} · {{ Money::format($row['authorized_cents']) }}</td>
                                <td class="pe-4 py-2 text-end">{{ $row['invoice_lines'] }} · {{ Money::format($row['invoice_cents']) }}</td>
                                <td class="pe-4 py-2 text-end">{{ $row['linked'] }}</td>
                                <td class="pe-4 py-2 text-end">{{ $row['authorizations_without_invoice'] }} · {{ Money::format($row['authorizations_without_invoice_cents']) }}</td>
                                <td class="pe-4 py-2 text-end">{{ $row['lines_without_authorization'] }} · {{ Money::format($row['lines_without_authorization_cents']) }}</td>
                                <td class="py-2 text-end">{{ $row['excluded'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-sm text-gray-600">{{ __('conciliation.reconciliation.cards.help') }}</p>
        @endif
    </section>

    @if ($card !== null)
        <section aria-labelledby="card-linked" class="flex flex-col gap-3 scroll-mt-4">
            <h3 id="card-linked" class="px-4 sm:px-0 text-base font-semibold text-gray-900">{{ __('conciliation.reconciliation.cards.linked_heading', ['card' => $card]) }}</h3>
            <livewire:reconciliation.links-table :session-id="$sessionId" :card="$card" :key="'card-links-'.$card" />
        </section>

        <section aria-labelledby="card-unpaid" class="flex flex-col gap-3">
            <h3 id="card-unpaid" class="px-4 sm:px-0 text-base font-semibold text-gray-900">{{ __('conciliation.reconciliation.cards.unpaid_heading', ['card' => $card]) }}</h3>
            <livewire:reconciliation.card-entries-table :session-id="$sessionId" :card="$card" kind="authorizations" :key="'card-unpaid-'.$card" />
        </section>

        <section aria-labelledby="card-orphans" class="flex flex-col gap-3">
            <h3 id="card-orphans" class="px-4 sm:px-0 text-base font-semibold text-gray-900">{{ __('conciliation.reconciliation.cards.orphans_heading', ['card' => $card]) }}</h3>
            <livewire:reconciliation.card-entries-table :session-id="$sessionId" :card="$card" kind="payments" :key="'card-orphans-'.$card" />
        </section>
    @endif
</div>
