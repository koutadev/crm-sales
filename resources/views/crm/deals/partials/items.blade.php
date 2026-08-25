@php
    use App\Support\DataTable\Column;

    $columns = [
        new Column('product', '商品'),
        new Column('quantity', '数量', align: 'right', wrap: false),
        new Column('unit_price', '税込単価', align: 'right', wrap: false),
        new Column('tax_rate_percent', '税率', align: 'center', wrap: false),
        new Column('amount_incl_tax', '税込金額', align: 'right', wrap: false),
        new Column('tax_amount', 'うち消費税', align: 'right', wrap: false),
    ];
@endphp

{{-- 明細(商品を選んだ時点の税込単価と税率を保持している) --}}
<x-card title="明細" :subtitle="'全 '.number_format($deal->items->count()).' 件。単価・税率は明細を作った時点の値を保持しています。'">
    @if ($canManage && ! $deal->trashed())
        <x-slot name="actions">
            <x-button :href="route('deals.edit', $deal->id)" variant="secondary" size="sm">明細を編集</x-button>
        </x-slot>
    @endif

    <x-table :columns="$columns" :is-empty="$deal->items->isEmpty()" empty="明細が登録されていません。">
        @foreach ($deal->items as $item)
            <x-table.row>
                <x-table.cell strong>
                    {{ $item->product?->name ?? '—' }}
                    @if ($item->product)
                        <span class="ms-2 font-mono text-xs font-normal text-gray-500 dark:text-gray-400">{{ $item->product->code }}</span>
                    @endif
                </x-table.cell>
                <x-table.cell align="right" :wrap="false">{{ number_format($item->quantity) }}</x-table.cell>
                <x-table.cell align="right" :wrap="false">{{ number_format($item->unit_price) }}</x-table.cell>
                <x-table.cell align="center" :wrap="false" muted>{{ $item->tax_rate_percent }}%</x-table.cell>
                <x-table.cell align="right" :wrap="false" strong>{{ number_format($item->amount_incl_tax) }}</x-table.cell>
                <x-table.cell align="right" :wrap="false" muted>{{ number_format($item->tax_amount) }}</x-table.cell>
            </x-table.row>
        @endforeach
    </x-table>

    <x-slot name="footer">
        <div class="flex flex-wrap items-center justify-end gap-6 text-sm">
            <span class="text-gray-600 dark:text-gray-400">
                税抜 <span class="tabular-nums">{{ number_format($summary->totalExclTax()) }}</span>
            </span>
            <span class="text-gray-600 dark:text-gray-400">
                消費税 <span class="tabular-nums">{{ number_format($summary->totalTax()) }}</span>
            </span>
            <span class="font-semibold text-gray-900 dark:text-gray-100">
                合計（税込） <span class="tabular-nums">{{ number_format($summary->totalInclTax()) }}</span>
            </span>
        </div>
    </x-slot>
</x-card>
