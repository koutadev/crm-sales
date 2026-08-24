{{-- 明細と金額内訳(内税。消費税は税率ごとに 1 回だけ切り捨てて逆算) --}}
<div class="space-y-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">明細</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            金額はすべて税込。消費税は税率ごとの税込合計から逆算（切り捨て）しています。
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/40">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">商品</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">数量</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">税込単価</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">税率</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">税込金額</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">うち消費税</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($deal->items as $item)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $item->product?->name ?? '—' }}</span>
                            @if ($item->product)
                                <span class="ms-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item->product->code }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format($item->quantity) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format($item->unit_price) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ $item->tax_rate_percent }}%</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ number_format($item->amount_incl_tax) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ number_format($item->tax_amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                            明細が登録されていません。
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 金額内訳 --}}
    <div class="grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 dark:border-gray-700 lg:grid-cols-2">
        {{-- 税率別 --}}
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">税率別の内訳</p>

            <table class="mt-2 w-full text-sm">
                <tbody>
                    @forelse ($summary->rateAmounts as $rate)
                        <tr class="border-b border-gray-100 last:border-b-0 dark:border-gray-700">
                            <td class="py-2 text-gray-600 dark:text-gray-400">{{ $rate->label() }} 対象（税込）</td>
                            <td class="py-2 text-right tabular-nums">{{ number_format($rate->amountInclTax) }}</td>
                            <td class="py-2 text-right text-xs text-gray-500 dark:text-gray-400">
                                うち消費税 <span class="tabular-nums">{{ number_format($rate->taxAmount) }}</span>
                                ／ 税抜 <span class="tabular-nums">{{ number_format($rate->amountExclTax()) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-2 text-gray-500 dark:text-gray-400">—</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 合計 --}}
        <div class="lg:ms-auto lg:w-72">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <dt>税抜</dt>
                    <dd class="tabular-nums">{{ number_format($summary->totalExclTax()) }}</dd>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                    <dt>消費税</dt>
                    <dd class="tabular-nums">{{ number_format($summary->totalTax()) }}</dd>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                    <dt>合計（税込）</dt>
                    <dd class="tabular-nums">{{ number_format($summary->totalInclTax()) }}</dd>
                </div>
            </dl>

            @if ($summary->totalInclTax() !== $deal->amount_total)
                <p class="mt-2 text-xs text-rose-600 dark:text-rose-400">
                    保存済みの合計（{{ number_format($deal->amount_total) }}）と明細の合計が一致していません。
                </p>
            @endif
        </div>
    </div>
</div>
