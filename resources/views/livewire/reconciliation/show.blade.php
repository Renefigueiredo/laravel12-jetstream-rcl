@php
    use App\Support\Money;

    $session = $this->session;
    $run = $this->run;
    $totals = $this->totals;
    $tabs = \App\Livewire\Reconciliation\Show::TABS;
@endphp

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('conciliation.reconciliation.title', ['session' => $session->label()]) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col gap-6">
            <div class="px-4 sm:px-0">
                <a href="{{ route('sessions.show', $session) }}" class="text-sm text-gray-700 underline hover:text-gray-900 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md">
                    {{ __('conciliation.reconciliation.back_to_session') }}
                </a>
            </div>

            @if ($run === null)
                <div role="status" class="bg-white shadow-xl sm:rounded-lg p-6 text-sm text-gray-900">
                    <p class="font-semibold">{{ __('conciliation.reconciliation.no_result.heading') }}</p>
                    <p class="text-gray-700">{{ __('conciliation.reconciliation.no_result.'.$session->status->value) }}</p>
                </div>
            @else
                <section aria-labelledby="reconciliation-summary" class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <h3 id="reconciliation-summary" class="text-base font-semibold text-gray-900">{{ __('conciliation.reconciliation.summary.heading') }}</h3>
                        <p class="text-sm text-gray-700">
                            {{ __('conciliation.reconciliation.summary.run', [
                                'name' => $run->requester->name,
                                'when' => $run->finished_at->timezone($timezone)->format('d/m/Y H:i'),
                                'tolerance' => Money::format($run->tolerance_cents),
                            ]) }}
                            @if ($run->tolerance_basis_points)
                                {{ __('conciliation.reconciliation.summary.tolerance_percent', ['percent' => rtrim(rtrim(number_format($run->tolerance_basis_points / 100, 2, ',', '.'), '0'), ',')]) }}
                            @endif
                            @if ($run->tolerance_basis_points && $run->tolerance_cap_cents)
                                {{ __('conciliation.reconciliation.summary.tolerance_cap', ['cap' => Money::format($run->tolerance_cap_cents)]) }}
                            @endif
                            {{ __('conciliation.reconciliation.summary.thresholds', ['automatic' => $run->automatic_threshold, 'suggestion' => $run->suggestion_threshold]) }}
                        </p>
                    </div>

                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6 text-sm">
                        @foreach ([
                            'authorizations' => $totals['authorizations'],
                            'reconciled_automatically' => $totals['reconciled_automatically'].' ('.$totals['automatic_percent'].'%)',
                            'reconciled_manually' => $totals['reconciled_manually'],
                            'awaiting_decision' => $totals['awaiting_decision'],
                            'doubtful' => $totals['doubtful'],
                            'partial' => $totals['partial'],
                            'excess' => $totals['excess'],
                            'unmatched_authorizations' => $totals['unmatched_authorizations'],
                            'open_balance' => $totals['open_balance'],
                            'unmatched_payments' => $totals['unmatched_payments'],
                            'prior_authorizations_linked' => $totals['prior_authorizations_linked'],
                            'paid_before_authorization' => $totals['paid_before_authorization'],
                            'payments_compared' => $totals['payments_compared'],
                            'excluded_payments' => $totals['excluded_payments'],
                            'with_difference' => $totals['with_difference']['count'].' · '.Money::format($totals['with_difference']['paid_less_cents']).' / '.Money::format($totals['with_difference']['paid_more_cents']),
                            'discount' => $totals['treatments']['discount']['count'].' · '.Money::format($totals['treatments']['discount']['cents']),
                            'accepted_surcharge' => $totals['treatments']['accepted_surcharge']['count'].' · '.Money::format($totals['treatments']['accepted_surcharge']['cents']),
                            'overpayment' => $totals['treatments']['overpayment']['count'].' · '.Money::format($totals['treatments']['overpayment']['cents']),
                        ] as $key => $value)
                            <div class="flex flex-col">
                                <dt class="text-gray-600">{{ __('conciliation.reconciliation.summary.'.$key) }}</dt>
                                <dd class="text-lg font-semibold text-gray-900">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                <div role="tablist" aria-label="{{ __('conciliation.reconciliation.tabs.label') }}" class="flex flex-wrap gap-2 px-4 sm:px-0">
                    @foreach ($tabs as $name)
                        <button type="button" role="tab" id="tab-{{ $name }}" aria-selected="{{ $tab === $name ? 'true' : 'false' }}" aria-controls="panel-{{ $name }}"
                            wire:click="showTab('{{ $name }}')"
                            @class([
                                'rounded-md px-4 py-2 text-sm font-semibold focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500',
                                'bg-gray-800 text-white' => $tab === $name,
                                'bg-white text-gray-800 hover:bg-gray-100 border border-gray-300' => $tab !== $name,
                            ])>
                            {{ __('conciliation.reconciliation.tabs.'.$name) }}
                        </button>
                    @endforeach
                </div>

                <div role="tabpanel" id="panel-{{ $tab }}" aria-labelledby="tab-{{ $tab }}">
                    @switch($tab)
                        @case('investigacao')
                            <livewire:reconciliation.investigation-table :session-id="$sessionId" :key="'investigation-'.$sessionId" />
                            @break
                        @case('conciliados')
                            <livewire:reconciliation.links-table :session-id="$sessionId" :key="'links-'.$sessionId" />
                            @break
                        @case('antes')
                            <div class="flex flex-col gap-6">
                                <section aria-labelledby="early-linked" class="flex flex-col gap-3">
                                    <h3 id="early-linked" class="px-4 sm:px-0 text-base font-semibold text-gray-900">{{ __('conciliation.reconciliation.early.linked_heading') }}</h3>
                                    <livewire:reconciliation.links-table :session-id="$sessionId" :only-paid-before="true" :key="'early-links-'.$sessionId" />
                                </section>

                                <section aria-labelledby="early-pending" class="flex flex-col gap-3">
                                    <h3 id="early-pending" class="px-4 sm:px-0 text-base font-semibold text-gray-900">{{ __('conciliation.reconciliation.early.pending_heading') }}</h3>
                                    <livewire:reconciliation.early-payments-table :session-id="$sessionId" :key="'early-'.$sessionId" />
                                </section>
                            </div>
                            @break
                        @case('cartoes')
                            <livewire:reconciliation.cards-table :session-id="$sessionId" :key="'cards-'.$sessionId" />
                            @break
                        @case('excluidos')
                            <livewire:reconciliation.excluded-table :session-id="$sessionId" :key="'excluded-'.$sessionId" />
                            @break
                        @default
                            <livewire:reconciliation.pending-table :session-id="$sessionId" :key="'pending-'.$sessionId" />
                    @endswitch
                </div>
            @endif
        </div>
    </div>
</div>
