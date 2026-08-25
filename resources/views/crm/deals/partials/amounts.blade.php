{{-- 金額内訳(内税。消費税は税率ごとの税込合計から 1 回だけ逆算して切り捨て) --}}
<x-card title="金額内訳" subtitle="金額はすべて税込。消費税は税率ごとの税込合計から逆算（切り捨て）しています。">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- 税率別 --}}
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">税率別</p>

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
                            <td class="py-2 text-gray-500 dark:text-gray-400">明細がないため内訳はありません。</td>
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
</x-card>
