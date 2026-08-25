@props([
    'label' => null,
    'actual' => 0,
    'target' => 0,
    'unit' => '円',
    'note' => null,
    'size' => 'md',
])

@php
    use App\Support\Ui\Achievement;

    $achievement = Achievement::of((int) $actual, (int) $target);

    $heights = ['sm' => 'h-2', 'md' => 'h-3', 'lg' => 'h-4'];
    $height = $heights[$size] ?? $heights['md'];
@endphp

{{--
    達成率のゲージ。

        <x-gauge label="当月" :actual="8200000" :target="10000000" unit="円" />

    目標が未設定（0 以下）のときは達成率を出さず、その旨を表示する。
    色は 未達 / 達成間近 / 達成 の 3 段階。
--}}
<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
        <div class="min-w-0">
            @if ($label !== null)
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
            @endif

            <p class="mt-0.5 flex items-baseline gap-1">
                <span class="text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((int) $actual) }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $unit }}</span>
            </p>
        </div>

        <div class="text-end">
            @if ($achievement->hasTarget())
                <p class="text-lg font-bold tabular-nums {{ $achievement->textClass() }}">{{ $achievement->rateLabel() }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    目標 <span class="tabular-nums">{{ number_format((int) $target) }}</span> {{ $unit }}
                </p>
            @else
                <p class="text-xs text-gray-500 dark:text-gray-400">目標未設定</p>
            @endif
        </div>
    </div>

    <div class="w-full overflow-hidden rounded-full bg-gray-100 {{ $height }} dark:bg-gray-700"
         role="progressbar"
         aria-valuemin="0"
         aria-valuemax="100"
         @if ($achievement->hasTarget()) aria-valuenow="{{ $achievement->rate() }}" @endif
         aria-label="{{ trim(($label ?? '').' 達成率') }}"
         aria-valuetext="{{ $achievement->description((int) $actual, (int) $target, $unit) }}">
        <div class="{{ $achievement->barClass() }} h-full rounded-full transition-[width] duration-500 motion-reduce:transition-none"
             style="width: {{ $achievement->barWidth() }}%"></div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
        <span class="{{ $achievement->textClass() }}">{{ $achievement->label() }}</span>

        @if ($note !== null)
            <span class="text-gray-500 dark:text-gray-400">{{ $note }}</span>
        @elseif ($achievement->hasTarget())
            <span class="text-gray-500 dark:text-gray-400">
                残り <span class="tabular-nums">{{ number_format($achievement->remaining((int) $actual, (int) $target)) }}</span> {{ $unit }}
            </span>
        @endif
    </div>
</div>
