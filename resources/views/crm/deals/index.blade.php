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

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                商談一覧
            </h2>

            @can(\App\Enums\PermissionName::MasterManage->value)
                <a href="{{ route('deals.create') }}"
                   class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-primary-hover">
                    商談を追加
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash />

            {{-- 絞り込み結果に連動する金額サマリ --}}
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

            <x-data-table :table="$table">
                {{-- 期間フィルタ(基準日を切り替えて絞り込む) --}}
                <x-slot name="extraFilters">
                    <x-form.segment name="period_basis" label="基準日"
                                    :options="$period['basisOptions']"
                                    :selected="$period['basis']" />

                    <div>
                        <label for="probability_min" class="block text-xs font-medium text-gray-600 dark:text-gray-400">確度</label>
                        <select id="probability_min" name="probability_min"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
                            <option value="">すべて</option>
                            @foreach (\App\Tables\DealTable::PROBABILITY_STEPS as $value => $label)
                                <option value="{{ $value }}" @selected($probabilityMin === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-56">
                        <x-date-range name="period" label="期間"
                                      :basis-label="$period['basisLabel']"
                                      :preset="$period['preset']"
                                      :from="$period['from']"
                                      :to="$period['to']" />
                    </div>
                </x-slot>

                @foreach ($table->items() as $deal)
                    <x-table.row :muted="$deal->trashed()">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">
                            <a href="{{ route('deals.show', $deal->id) }}"
                               class="text-primary-text hover:text-primary-hover hover:underline">
                                {{ $deal->code }}
                            </a>
                        </td>

                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                            @if ($deal->partner)
                                <a href="{{ route('customers.show', $deal->partner_id) }}" class="hover:underline">
                                    {{ $deal->partner->name }}
                                </a>
                            @else
                                &mdash;
                            @endif
                        </td>

                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('deals.show', $deal->id) }}" class="hover:underline">{{ $deal->title }}</a>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-center">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $deal->status->badgeClass() }}">
                                {{ $deal->status->label() }}
                            </span>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                            {{ $deal->probability }}%
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">
                            {{ number_format($deal->amount_total) }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                            {{ $deal->expected_close_date->format('Y/m/d') }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                            {{ $deal->ordered_at?->format('Y/m/d') ?? '—' }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                            {{ $deal->employee?->name ?? '—' }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($deal->trashed())
                                @if (auth()->user()?->isAdmin())
                                    <form method="POST" action="{{ route('deals.restore', $deal->id) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="text-xs font-medium text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                                                onclick="return confirm('この商談を復元しますか?')">
                                            復元
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">&mdash;</span>
                                @endif
                            @else
                                <a href="{{ route('deals.show', $deal->id) }}"
                                   class="text-xs font-medium text-primary-text hover:text-primary-hover">
                                    詳細
                                </a>

                                @can(\App\Enums\PermissionName::MasterManage->value)
                                    <a href="{{ route('deals.edit', $deal->id) }}"
                                       class="ms-3 text-xs font-medium text-primary-text hover:text-primary-hover">
                                        編集
                                    </a>
                                @endcan
                            @endif
                        </td>
                    </x-table.row>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>
