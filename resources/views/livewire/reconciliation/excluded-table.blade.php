<div class="flex flex-col gap-6">
    <section aria-labelledby="excluded-codes-of-run" class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-col gap-3">
        <h3 id="excluded-codes-of-run" class="text-base font-semibold text-gray-900">{{ __('conciliation.reconciliation.excluded.codes_heading') }}</h3>

        @if ($this->codes === [])
            <p class="text-sm text-gray-700">{{ __('conciliation.reconciliation.excluded.no_codes') }}</p>
        @else
            <div class="max-h-64 overflow-auto" tabindex="0">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="pe-4 py-1">{{ __('conciliation.excluded_codes.code') }}</th>
                            <th scope="col" class="py-1 text-end">{{ __('conciliation.reconciliation.excluded.payments') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->codes as $code => $payments)
                            <tr wire:key="excluded-code-{{ $code }}" class="border-t border-gray-200">
                                <td class="pe-4 py-1">{{ $code }}</td>
                                <td class="py-1 text-end">{{ $payments }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>
