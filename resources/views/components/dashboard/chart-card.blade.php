@props(['chart', 'height' => '18rem'])

@php
    /** @var \App\Support\Dashboard\Chart $chart */
@endphp

<div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $chart->title }}</h3>

    <div class="mt-4" style="height: {{ $height }}">
        @if ($chart->isEmpty())
            <p class="flex h-full items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                表示できるデータがありません。
            </p>
        @else
            {{-- 設定は PHP 側で組み立て、JSON として渡す（resources/js/charts.js が描画する） --}}
            <canvas id="chart-{{ $chart->id }}"
                    data-chart="{{ json_encode($chart->toChartJs(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"></canvas>
        @endif
    </div>
</div>
