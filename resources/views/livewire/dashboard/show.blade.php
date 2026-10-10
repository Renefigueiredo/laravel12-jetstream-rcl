@php
    use App\Livewire\Dashboard\Show;
    use App\Support\Money;

    $totals = $this->totals;
    $alerts = $this->alerts;
@endphp

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('conciliation.dashboard.title') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col gap-6">
            @unless ($this->hasProcessedSession)
                <div role="status" class="bg-white shadow-xl sm:rounded-lg p-6 text-sm text-gray-900">
                    <p class="font-semibold">{{ __('conciliation.dashboard.no_session.heading') }}</p>
                    <p class="text-gray-700">
                        {{ __('conciliation.dashboard.no_session.body') }}
                        <a href="{{ route('sessions.index') }}" class="underline text-indigo-800 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md">{{ __('conciliation.dashboard.no_session.link') }}</a>
                    </p>
                </div>
            @endunless

            <section aria-labelledby="dashboard-summary" class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-col gap-4">
                <h3 id="dashboard-summary" class="text-base font-semibold text-gray-900">{{ __('conciliation.dashboard.summary.heading') }}</h3>

                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 text-sm">
                    @foreach ([
                        'authorized' => Money::format($totals['authorized_cents']),
                        'paid' => Money::format($totals['paid_cents']),
                        'balance' => Money::format($totals['balance_cents']),
                        'discount' => Money::format($totals['discount_cents']),
                        'accepted_surcharge' => Money::format($totals['accepted_surcharge_cents']),
                        'overpayment' => Money::format($totals['overpayment_cents']),
                        'open' => $totals['open'],
                        'partial' => $totals['partial'],
                        'reconciled' => $totals['reconciled'],
                    ] as $key => $value)
                        <div class="flex flex-col">
                            <dt class="text-gray-600">{{ __('conciliation.dashboard.summary.'.$key) }}</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @if (array_sum($alerts) > 0)
                <section aria-labelledby="dashboard-alerts" class="flex flex-col gap-2 px-4 sm:px-0">
                    <h3 id="dashboard-alerts" class="sr-only">{{ __('conciliation.dashboard.alerts.heading') }}</h3>
                    <ul class="flex flex-wrap gap-3 text-sm">
                        @foreach ([
                            'overdue' => ['aba' => 'abertas', 'filtros' => ['overdue' => ['isActive' => true]]],
                            'overpaid' => ['aba' => 'conciliadas', 'filtros' => ['overpaid' => ['isActive' => true]]],
                            'divergences' => ['aba' => 'divergencias'],
                        ] as $alert => $parameters)
                            @if ($alerts[$alert] > 0)
                                <li>
                                    <a href="{{ route('dashboard', $parameters) }}" wire:key="alert-{{ $alert }}"
                                        class="inline-flex items-center gap-2 rounded-md border border-amber-400 bg-amber-50 px-3 py-2 font-semibold text-amber-900 hover:bg-amber-100 focus:outline-2 focus:outline-offset-2 focus:outline-amber-600">
                                        {{ trans_choice('conciliation.dashboard.alerts.'.$alert, $alerts[$alert], ['count' => $alerts[$alert]]) }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </section>
            @endif

            <div role="tablist" aria-label="{{ __('conciliation.dashboard.tabs.label') }}" class="flex flex-wrap gap-2 px-4 sm:px-0">
                @foreach (Show::TABS as $name)
                    <button type="button" role="tab" id="tab-{{ $name }}" aria-selected="{{ $tab === $name ? 'true' : 'false' }}" aria-controls="panel-{{ $name }}"
                        wire:click="showTab('{{ $name }}')"
                        @class([
                            'rounded-md px-4 py-2 text-sm font-semibold focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500',
                            'bg-gray-800 text-white' => $tab === $name,
                            'bg-white text-gray-800 hover:bg-gray-100 border border-gray-300' => $tab !== $name,
                        ])>
                        {{ __('conciliation.dashboard.tabs.'.$name) }}
                    </button>
                @endforeach
            </div>

            <div role="tabpanel" id="panel-{{ $tab }}" aria-labelledby="tab-{{ $tab }}">
                @switch($tab)
                    @case('conciliadas')
                        <livewire:dashboard.authorizations-table scope="reconciled" :key="'dashboard-reconciled'" />
                        @break
                    @case('divergencias')
                        <livewire:dashboard.divergences-table :key="'dashboard-divergences'" />
                        @break
                    @default
                        <livewire:dashboard.authorizations-table scope="open" :key="'dashboard-open'" />
                @endswitch
            </div>
        </div>
    </div>
</div>
