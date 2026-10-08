@php
    use App\Enums\ImportAttemptStatus;

    $inputId = 'upload-'.$slot->value;
    $headingId = 'slot-'.$slot->value;
    $attemptStatus = $attempt?->status;
@endphp

<section wire:key="slot-{{ $slot->value }}" aria-labelledby="{{ $headingId }}" class="bg-white shadow-xl sm:rounded-lg p-6 flex flex-col gap-4">
    <div class="flex items-start justify-between gap-3">
        <h3 id="{{ $headingId }}" class="text-base font-semibold text-gray-900">{{ $slot->label() }}</h3>

        @if ($file)
            <span class="shrink-0 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-900">{{ __('conciliation.slots.status.loaded') }}</span>
        @else
            <span class="shrink-0 rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-800">{{ __('conciliation.slots.status.pending') }}</span>
        @endif
    </div>

    @if ($file)
        <dl class="flex flex-col gap-1 text-sm text-gray-700">
            <div class="flex flex-wrap gap-1">
                <dt class="font-semibold text-gray-900">{{ __('conciliation.slots.file') }}:</dt>
                <dd class="break-all">{{ $file->original_name }}</dd>
            </div>
            <dd>{{ trans_choice('conciliation.slots.entries', $file->rows_imported, ['count' => $file->rows_imported]) }}</dd>

            @if ($file->rows_skipped_value > 0)
                <dd>{{ trans_choice('conciliation.slots.skipped_value', $file->rows_skipped_value, ['count' => $file->rows_skipped_value]) }}</dd>
            @endif

            @if ($file->rows_skipped_existing > 0)
                <dd>{{ trans_choice('conciliation.slots.skipped_existing', $file->rows_skipped_existing, ['count' => $file->rows_skipped_existing]) }}</dd>
            @endif

            <dd>
                {{ __('conciliation.slots.uploaded_by', ['name' => $file->uploader->name, 'when' => $file->created_at->timezone($timezone)->format('d/m/Y H:i')]) }}
            </dd>
        </dl>

        @if ($file->period_divergence)
            <p class="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">
                {{ trans_choice('conciliation.slots.period_divergence', $file->rows_out_of_period, [
                    'count' => $file->rows_out_of_period,
                    'from' => $file->min_date?->format('d/m/Y'),
                    'to' => $file->max_date?->format('d/m/Y'),
                ]) }}
            </p>
        @endif

        @if ($file->sheet_count > 1)
            <p class="text-sm text-gray-700">{{ __('conciliation.slots.first_sheet_only', ['count' => $file->sheet_count]) }}</p>
        @endif

        @if ($file->missing_columns)
            <p class="text-sm text-gray-700">{{ __('conciliation.slots.missing_columns', ['columns' => implode(', ', $file->missing_columns)]) }}</p>
        @endif
    @endif

    @if ($slotMessage)
        <p role="status" @class([
            'rounded-md border p-3 text-sm',
            'border-red-300 bg-red-50 text-red-900' => $slotMessage['style'] === 'danger',
            'border-gray-300 bg-gray-50 text-gray-900' => $slotMessage['style'] !== 'danger',
        ])>{{ $slotMessage['text'] }}</p>
    @endif

    @if ($attemptStatus?->isInProgress())
        <div role="status" class="flex flex-col gap-2 text-sm text-gray-900">
            <p class="font-semibold">{{ __('conciliation.import.in_progress', ['percent' => $attempt->progress]) }}</p>
            <progress class="w-full" max="100" value="{{ $attempt->progress }}"
                aria-label="{{ __('conciliation.import.in_progress', ['percent' => $attempt->progress]) }}"></progress>
            <p class="break-all text-gray-700">{{ $attempt->original_name }}</p>
        </div>
    @elseif ($attemptStatus === ImportAttemptStatus::AwaitingConfirmation)
        <div role="alert" class="flex flex-col gap-3 rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">
            <p class="font-semibold">{{ __('conciliation.import.divergence.heading') }}</p>
            <p class="break-all">{{ $attempt->original_name }}</p>
            <p>
                {{ trans_choice('conciliation.import.divergence.body', $attempt->rows_out_of_period, [
                    'count' => $attempt->rows_out_of_period,
                    'period' => $session->periodLabel(),
                    'from' => $attempt->min_date?->format('d/m/Y'),
                    'to' => $attempt->max_date?->format('d/m/Y'),
                ]) }}
            </p>
            <div class="flex flex-wrap gap-3">
                <x-button type="button" wire:click="confirmDivergence({{ $attempt->id }})" wire:loading.attr="disabled">
                    {{ __('conciliation.import.divergence.confirm') }}
                </x-button>
                <x-secondary-button type="button" wire:click="cancelAttempt({{ $attempt->id }})" wire:loading.attr="disabled">
                    {{ __('conciliation.import.divergence.cancel') }}
                </x-secondary-button>
            </div>
        </div>
    @elseif ($attemptStatus === ImportAttemptStatus::Rejected)
        <div role="alert" class="flex flex-col gap-2 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
            <p class="font-semibold">{{ __('conciliation.import.rejected') }}</p>
            <p class="break-all">{{ $attempt->original_name }}</p>

            @if ($attempt->message)
                <p>{{ $attempt->message }}</p>
            @endif

            @if ($attempt->error_count > 0)
                <p>{{ trans_choice('conciliation.import.error_total', $attempt->error_count, ['count' => $attempt->error_count]) }}</p>
                <p class="font-semibold">{{ __('conciliation.import.first_errors') }}</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead>
                            <tr>
                                <th scope="col" class="pe-3 py-1">{{ __('conciliation.import.report.row') }}</th>
                                <th scope="col" class="pe-3 py-1">{{ __('conciliation.import.report.column') }}</th>
                                <th scope="col" class="pe-3 py-1">{{ __('conciliation.import.report.value') }}</th>
                                <th scope="col" class="py-1">{{ __('conciliation.import.report.reason') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attempt->first_errors ?? [] as $index => $error)
                                <tr wire:key="error-{{ $attempt->id }}-{{ $index }}" class="border-t border-red-200 align-top">
                                    <td class="pe-3 py-1">{{ $error['row'] }}</td>
                                    <td class="pe-3 py-1">{{ $error['column'] }}</td>
                                    <td class="pe-3 py-1 break-all">{{ $error['value'] }}</td>
                                    <td class="py-1">{{ $error['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($attempt->error_report_path)
                    <a href="{{ route('sessions.attempts.errors', $attempt) }}" class="font-semibold underline focus:outline-2 focus:outline-offset-2 focus:outline-red-700 rounded-md">
                        {{ __('conciliation.import.download_errors') }}
                    </a>
                @endif
            @endif
        </div>
    @elseif ($attemptStatus === ImportAttemptStatus::Failed)
        <p role="alert" class="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
            {{ $attempt->message ?? __('conciliation.import.failed') }}
        </p>
    @endif

    <div class="mt-auto flex flex-col gap-3">
        @if ($isOpen)
            <div class="flex flex-col gap-2">
                <label for="{{ $inputId }}" class="text-sm font-medium text-gray-900">
                    {{ $file ? __('conciliation.slots.replace') : __('conciliation.slots.choose_file') }}
                </label>
                <input id="{{ $inputId }}" type="file" accept=".xlsx,.csv" wire:model="uploads.{{ $slot->value }}"
                    class="block w-full text-sm text-gray-900 file:me-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-4 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md" />
                <p wire:loading wire:target="uploads.{{ $slot->value }}" role="status" class="text-sm text-gray-700">
                    {{ __('conciliation.slots.uploading') }}
                </p>
                <x-input-error for="uploads.{{ $slot->value }}" />
            </div>
        @endif

        <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
            @if ($file)
                <a href="{{ route('sessions.files.download', [$session, $file]) }}" class="text-gray-800 underline hover:text-gray-900 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md">
                    {{ __('conciliation.slots.download_original') }}
                </a>
            @endif

            <a href="{{ route('templates.download', $slot->layout()->slug()) }}" class="text-gray-800 underline hover:text-gray-900 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-500 rounded-md">
                {{ __('conciliation.slots.download_template') }}
            </a>
        </div>
    </div>
</section>
