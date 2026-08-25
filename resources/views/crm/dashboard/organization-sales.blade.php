@php
    /** @var \App\Support\Crm\OrganizationSales $organizationSales */

    // 地域 > エリア > 店舗 > 担当者 を 1 枚の表に平らに並べ、開閉は Alpine で行う
    $rows = [];

    $flatten = function (array $nodes, ?string $parentKey) use (&$flatten, &$rows): void {
        foreach ($nodes as $node) {
            $rows[] = ['node' => $node, 'parent' => $parentKey];

            if ($node->hasChildren()) {
                $flatten($node->children, $node->key);
            }
        }
    };

    $flatten($organizationSales->regions, null);

    // 都道府県で束ねた見方(階層とは別の切り口)。追加のクエリは要らない
    $prefectureRows = [];

    $flattenPrefecture = function (array $nodes, ?string $parentKey) use (&$flattenPrefecture, &$prefectureRows): void {
        foreach ($nodes as $node) {
            $prefectureRows[] = ['node' => $node, 'parent' => $parentKey];

            if ($node->hasChildren()) {
                $flattenPrefecture($node->children, $node->key);
            }
        }
    };

    $flattenPrefecture($organizationSales->prefectures, null);

    $parentMap = [];

    foreach (array_merge($rows, $prefectureRows) as $row) {
        $parentMap[$row['node']->key] = $row['parent'];
    }

    $axes = [
        'hierarchy' => ['label' => '階層（地域 → エリア → 店舗）', 'rows' => $rows],
        'prefecture' => ['label' => '都道府県', 'rows' => $prefectureRows],
    ];
@endphp

<x-card title="組織別の売上（受注・税込）"
        subtitle="担当者の所属をたどって積み上げています。行の ▸ で掘り下げられます。当月ぶんは目標と並べて達成率を出します。">
    @if ($organizationSales->isEmpty())
        <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">組織が登録されていません。</p>
    @else
        <div x-data="{
                 axis: 'hierarchy',
                 open: {},
                 parents: @js($parentMap),
                 toggle(key) { this.open[key] = ! this.open[key] },
                 visible(parent) {
                     // 親をすべて開いているときだけ表示する
                     while (parent) {
                         if (! this.open[parent]) { return false; }
                         parent = this.parents[parent] ?? null;
                     }
                     return true;
                 },
             }">

            {{-- 見る切り口(階層 / 都道府県)。集計はどちらも同じ 1 回の読み込みから作っている --}}
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">集計の切り口</span>

                <div class="inline-flex rounded-md border border-gray-300 p-0.5 text-xs dark:border-gray-700"
                     role="radiogroup" aria-label="集計の切り口">
                    @foreach ($axes as $key => $axis)
                        <button type="button"
                                role="radio"
                                :aria-checked="(axis === @js($key)).toString()"
                                x-on:click="axis = @js($key)"
                                :class="axis === @js($key)
                                    ? 'bg-primary text-white'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                                class="rounded px-3 py-1 font-medium transition-colors motion-reduce:transition-none">
                            {{ $axis['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="overflow-x-auto">
            @foreach ($axes as $axisKey => $axis)
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                   x-show="axis === @js($axisKey)" @if ($axisKey !== 'hierarchy') x-cloak @endif>
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $axisKey === 'hierarchy' ? '組織 / 担当者' : '都道府県 / 店舗' }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">受注件数</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">受注売上（税込・累計）</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">構成比</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">当月実績</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">当月目標</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">達成率</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($axis['rows'] as $row)
                        @php
                            $node = $row['node'];
                            $achievement = $node->achievement();
                        @endphp

                        <tr @if ($row['parent'] !== null) x-show="visible(@js($row['parent']))" x-cloak @endif
                            class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2" style="padding-inline-start: {{ ($node->depth - 1) * 1.5 }}rem">
                                    @if ($node->hasChildren())
                                        <button type="button"
                                                x-on:click="toggle(@js($node->key))"
                                                :aria-expanded="(!! open[@js($node->key)]).toString()"
                                                :aria-label="(open[@js($node->key)] ? '閉じる：' : '開く：') + @js($node->name)"
                                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                                            <span class="text-xs" x-text="open[@js($node->key)] ? '▾' : '▸'">▸</span>
                                        </button>
                                    @else
                                        <span class="h-5 w-5 shrink-0" aria-hidden="true"></span>
                                    @endif

                                    <span class="whitespace-nowrap text-xs text-gray-400 dark:text-gray-500">{{ $node->typeLabel }}</span>
                                    <span @class(['font-medium' => $node->depth <= 2])>{{ $node->name }}</span>
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                {{ number_format($node->dealCount) }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-2 text-right font-medium tabular-nums">
                                {{ number_format($node->amountInclTax) }}
                            </td>

                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div class="h-full rounded-full bg-primary"
                                             style="width: {{ $node->share($organizationSales->totalInclTax) }}%"></div>
                                    </div>
                                    <span class="tabular-nums text-xs text-gray-500 dark:text-gray-400">
                                        {{ $node->share($organizationSales->totalInclTax) }}%
                                    </span>
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">
                                {{ number_format($node->monthAmount) }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                {{ $node->monthTarget > 0 ? number_format($node->monthTarget) : '—' }}
                            </td>

                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700"
                                         role="progressbar"
                                         aria-valuemin="0" aria-valuemax="100"
                                         @if ($achievement->hasTarget()) aria-valuenow="{{ $achievement->rate() }}" @endif
                                         aria-valuetext="{{ $achievement->description($node->monthAmount, $node->monthTarget, '円') }}"
                                         aria-label="{{ $node->name }} の達成率">
                                        <div class="{{ $achievement->barClass() }} h-full rounded-full"
                                             style="width: {{ $achievement->barWidth() }}%"></div>
                                    </div>
                                    <span class="tabular-nums text-xs {{ $achievement->textClass() }}">
                                        {{ $achievement->rateLabel() }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot class="border-t-2 border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold">合計</th>
                        <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums">
                            {{ number_format($organizationSales->dealCount) }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums">
                            {{ number_format($organizationSales->totalInclTax) }}
                        </td>
                        <td></td>
                        <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums">
                            {{ number_format($organizationSales->monthAmount) }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums">
                            {{ $organizationSales->monthTarget > 0 ? number_format($organizationSales->monthTarget) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold {{ $organizationSales->achievement()->textClass() }}">
                            {{ $organizationSales->achievement()->rateLabel() }}
                        </td>
                    </tr>
                </tfoot>
            </table>
            @endforeach
            </div>
        </div>
    @endif
</x-card>
