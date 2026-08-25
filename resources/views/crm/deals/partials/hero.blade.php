@php
    use App\Support\Dashboard\Kpi;

    /** @var \App\Support\Crm\AmountSummary $summary */
    // 金額はすべて明細から計算した値(表示のためにここで計算し直すことはしない)
    $weighted = (int) floor($deal->amount_total * $deal->probability / 100);

    $kpis = [
        new Kpi(label: '合計(税込)', value: $summary->totalInclTax(), unit: '円', note: '明細 '.number_format($deal->items->count()).' 件'),
        new Kpi(label: 'うち消費税', value: $summary->totalTax(), unit: '円', note: '税率ごとに 1 回だけ切り捨て'),
        new Kpi(label: '税抜', value: $summary->totalExclTax(), unit: '円', note: '税込 − 消費税'),
        new Kpi(label: '加重見込み(税込)', value: $weighted, unit: '円', note: '確度 '.$deal->probability.'%'),
    ];
@endphp

{{-- 金額の要点(1 ページの冒頭で金額の全体像が分かるようにする) --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($kpis as $kpi)
        <x-kpi-card :kpi="$kpi" />
    @endforeach
</div>
