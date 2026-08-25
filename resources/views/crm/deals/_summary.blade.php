@php
    use App\Support\Dashboard\Kpi;

    /** @var \App\Support\Crm\DealListSummary $summary */
    // 絞り込み結果に連動した集計(金額はすべて税込)
    $kpis = [
        new Kpi(label: '件数', value: $summary->dealCount, unit: '件',
                note: $summary->dealCount > 0 ? '平均 '.number_format($summary->averageInclTax()).' 円' : '該当なし'),
        new Kpi(label: '合計(税込)', value: $summary->totalInclTax, unit: '円', note: '表示中の商談の合計'),
        new Kpi(label: '受注済み(税込)', value: $summary->wonTotal, unit: '円', note: '確定した売上'),
        new Kpi(label: '加重見込み(税込)', value: $summary->weightedOpenTotal, unit: '円',
                note: '進行中 '.number_format($summary->openTotal).' 円 × 確度'),
    ];
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($kpis as $kpi)
        <x-kpi-card :kpi="$kpi" />
    @endforeach
</div>

{{-- ステータス別の内訳(金額 / 件数を切り替え。どちらも同じ 1 クエリの結果) --}}
<div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
     x-data="{ measure: 'amount' }">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
            ステータス別の内訳
        </h3>

        <div class="inline-flex rounded-md border border-gray-300 p-0.5 text-xs dark:border-gray-700"
             role="radiogroup" aria-label="内訳の表示単位">
            @foreach (['amount' => '金額', 'count' => '件数'] as $value => $label)
                <button type="button"
                        role="radio"
                        :aria-checked="(measure === '{{ $value }}').toString()"
                        x-on:click="measure = '{{ $value }}'"
                        :class="measure === '{{ $value }}'
                            ? 'bg-primary text-white'
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                        class="rounded px-3 py-1 font-medium transition-colors motion-reduce:transition-none">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div x-show="measure === 'amount'">
        <x-stacked-bar unit=" 円" :segments="$summary->statusSegments('amount')"
                       empty="表示中の商談がありません。" />
    </div>

    <div x-show="measure === 'count'" x-cloak>
        <x-stacked-bar unit=" 件" :segments="$summary->statusSegments('count')"
                       empty="表示中の商談がありません。" />
    </div>
</div>
