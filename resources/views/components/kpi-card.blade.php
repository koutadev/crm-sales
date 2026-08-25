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

    // カード幅に合わせて数字を伸縮させるため、コンテナクエリを有効にする
    $classes = '@container block rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800';

    // ホバー時に出す全桁(単位まで含める)
    $fullValue = trim($value.' '.$unit);
@endphp

{{--
    KPI カード。href を渡すとカード全体がリンクになる。

    数字はカード幅に合わせて自動で縮み、それでも入りきらないときだけ末尾を「…」にする。
    省略されても、ホバー(title)で全桁を確認できる。単位は数字と同じ行に留まる。
--}}
<{{ $href ? 'a' : 'div' }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => $classes.($href ? ' transition hover:border-primary hover:shadow-sm motion-reduce:transition-none' : '')]) }}>

    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>

    <p class="mt-2 flex min-w-0 items-baseline gap-1" title="{{ $fullValue }}">
        {{-- 3xl を上限に、カード幅(cqi)に応じて縮める。入りきらなければ末尾を省略 --}}
        <span class="min-w-0 truncate text-[length:clamp(1.125rem,9cqi,1.875rem)] font-bold leading-tight tabular-nums text-gray-900 dark:text-gray-100">{{ $value }}</span>
        @if ($unit !== '')
            <span class="shrink-0 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $unit }}</span>
        @endif
    </p>

    @if ($note)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $note }}</p>
    @endif
</{{ $href ? 'a' : 'div' }}>
