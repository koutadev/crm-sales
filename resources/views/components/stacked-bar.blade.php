@props([
    'segments' => [],
    'unit' => '',
    'total' => null,
    'empty' => 'データがありません',
    'height' => 'h-3',
    'legend' => true,
])

@php
    /**
     * 構成比を横棒で見せる。
     *
     *   $segments … [['label' => '受注', 'value' => 120000, 'class' => 'bg-emerald-500'], …]
     *
     * value は 0 以上の整数(金額でも件数でもよい)。
     * total を渡さなければ、segments の合計を 100% とする。
     */
    $items = [];

    foreach ($segments as $segment) {
        $items[] = [
            'label' => (string) ($segment['label'] ?? ''),
            'value' => max(0, (int) ($segment['value'] ?? 0)),
            'class' => (string) ($segment['class'] ?? 'bg-gray-400'),
        ];
    }

    $sum = $total !== null ? max(0, (int) $total) : array_sum(array_column($items, 'value'));

    // 極端に小さい構成比でも見えるように、0 でなければ最低幅を持たせる
    $share = static fn (int $value): float => $sum > 0 ? round($value / $sum * 100, 1) : 0.0;
    $width = static fn (float $percent): float => $percent > 0 ? max($percent, 1.2) : 0.0;
@endphp

<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @if ($sum <= 0)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
    @else
        <div class="flex w-full overflow-hidden rounded-full bg-gray-100 {{ $height }} dark:bg-gray-700"
             role="img"
             aria-label="{{ implode('、', array_map(fn (array $item): string => $item['label'].' '.number_format($item['value']).$unit, $items)) }}">
            @foreach ($items as $item)
                @continue($item['value'] <= 0)
                <div class="{{ $item['class'] }} transition-[width] duration-300 motion-reduce:transition-none"
                     style="width: {{ $width($share($item['value'])) }}%"
                     title="{{ $item['label'] }}：{{ number_format($item['value']) }}{{ $unit }}（{{ $share($item['value']) }}%）"></div>
            @endforeach
        </div>

        @if ($legend)
            <ul class="flex flex-wrap gap-x-5 gap-y-2 text-xs">
                @foreach ($items as $item)
                    <li class="flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm {{ $item['class'] }}" aria-hidden="true"></span>
                        <span class="text-gray-600 dark:text-gray-400">{{ $item['label'] }}</span>
                        <span class="font-medium tabular-nums text-gray-900 dark:text-gray-100">
                            {{ number_format($item['value']) }}{{ $unit }}
                        </span>
                        <span class="tabular-nums text-gray-400 dark:text-gray-500">{{ $share($item['value']) }}%</span>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
