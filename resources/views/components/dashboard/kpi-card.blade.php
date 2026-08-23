@props(['kpi'])

@php
    /** @var \App\Support\Dashboard\Kpi $kpi */
    $classes = 'block rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800';
@endphp

@if ($kpi->href)
    <a href="{{ $kpi->href }}" class="{{ $classes }} transition hover:border-primary hover:shadow-sm">
@else
    <div class="{{ $classes }}">
@endif

    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {{ $kpi->label }}
    </p>

    <p class="mt-2 flex items-baseline gap-1">
        <span class="text-3xl font-bold tabular-nums text-gray-900 dark:text-gray-100">
            {{ $kpi->formattedValue() }}
        </span>
        @if ($kpi->unit !== '')
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $kpi->unit }}</span>
        @endif
    </p>

    @if ($kpi->note)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kpi->note }}</p>
    @endif

@if ($kpi->href)
    </a>
@else
    </div>
@endif
