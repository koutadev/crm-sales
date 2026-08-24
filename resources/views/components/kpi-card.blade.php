@props([
    'label',
    'value',
    'unit' => '',
    'note' => null,
    'href' => null,
    'kpi' => null,
])

@php
    // App\Support\Dashboard\Kpi をそのまま渡すこともできる
    if ($kpi !== null) {
        $label = $kpi->label;
        $value = $kpi->formattedValue();
        $unit = $kpi->unit;
        $note = $kpi->note;
        $href = $kpi->href;
    } elseif (is_int($value)) {
        $value = number_format($value);
    }

    $classes = 'block rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800';
@endphp

{{-- KPI カード。href を渡すとカード全体がリンクになる。 --}}
<{{ $href ? 'a' : 'div' }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => $classes.($href ? ' transition hover:border-primary hover:shadow-sm motion-reduce:transition-none' : '')]) }}>

    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>

    <p class="mt-2 flex items-baseline gap-1">
        <span class="text-3xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $value }}</span>
        @if ($unit !== '')
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $unit }}</span>
        @endif
    </p>

    @if ($note)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $note }}</p>
    @endif
</{{ $href ? 'a' : 'div' }}>
