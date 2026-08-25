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

    $parentMap = [];

    foreach ($rows as $row) {
        $parentMap[$row['node']->key] = $row['parent'];
    }
@endphp

<x-card title="組織別の売上（受注・税込）"
        subtitle="担当者の所属をたどって、地域 → エリア → 店舗 → 担当者 に積み上げています。行の ▸ で掘り下げられます。">
    @if ($organizationSales->isEmpty())
        <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">組織が登録されていません。</p>
    @else
        <div x-data="{
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
             }"
             class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">組織 / 担当者</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">受注件数</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">受注売上（税込）</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">構成比</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($rows as $row)
                        @php $node = $row['node']; @endphp

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
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</x-card>
