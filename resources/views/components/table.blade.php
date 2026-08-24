@props([
    'columns' => [],
    'sort' => null,
    'direction' => 'asc',
    'sortUrl' => null,
    'actions' => false,
    'actionsLabel' => '操作',
    'empty' => 'データがありません。',
    'isEmpty' => false,
    'loading' => false,
    'loadingRows' => 5,
])

@php
    use App\Support\DataTable\Column;

    // Column オブジェクトでも配列でも受け取れる
    $items = [];

    foreach ($columns as $column) {
        $items[] = $column instanceof Column ? $column : Column::fromArray((array) $column);
    }

    $columnCount = count($items) + ($actions ? 1 : 0);
@endphp

{{--
    一覧テーブル。

        <x-table :columns="$columns" :sort="$sort" :direction="$direction"
                 :sort-url="fn ($column) => route('…', ['sort' => $column->key])"
                 :is-empty="$rows->isEmpty()" actions>
            @foreach ($rows as $row)
                <x-table.row :href="route('…', $row->id)">
                    <x-table.cell>{{ $row->name }}</x-table.cell>
                    …
                </x-table.row>
            @endforeach
        </x-table>

    共通一覧基盤(TableDefinition)を使う画面は <x-data-table> が中でこれを使う。
--}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800']) }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900/40">
            <tr>
                @foreach ($items as $column)
                    @php
                        $sorted = $sort !== null && $column->sortable && $sort === $column->key;
                        $ariaSort = $sorted ? ($direction === 'asc' ? 'ascending' : 'descending') : ($column->sortable ? 'none' : null);
                    @endphp

                    <th scope="col"
                        @if ($ariaSort) aria-sort="{{ $ariaSort }}" @endif
                        @class([
                            'px-4 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400',
                            $column->alignClass(),
                            $column->width => $column->width !== null,
                        ])>
                        @if ($column->sortable && $sortUrl !== null)
                            <a href="{{ $sortUrl($column) }}"
                               class="inline-flex items-center gap-1 rounded transition-colors hover:text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary motion-reduce:transition-none dark:hover:text-gray-100">
                                {{ $column->label }}
                                <span class="text-[10px]" aria-hidden="true">{{ $sorted ? ($direction === 'asc' ? '▲' : '▼') : '' }}</span>
                            </a>
                        @else
                            {{ $column->label }}
                        @endif
                    </th>
                @endforeach

                @if ($actions)
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $actionsLabel }}
                    </th>
                @endif
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @if ($loading)
                {{-- 読み込み中の骨組み --}}
                @for ($row = 0; $row < $loadingRows; $row++)
                    <tr aria-hidden="true">
                        @for ($cell = 0; $cell < $columnCount; $cell++)
                            <td class="px-4 py-3">
                                <span class="block h-3 w-full animate-pulse rounded bg-gray-200 motion-reduce:animate-none dark:bg-gray-700"></span>
                            </td>
                        @endfor
                    </tr>
                @endfor
            @elseif ($isEmpty)
                <tr>
                    <td colspan="{{ $columnCount }}" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                        {{ $empty }}
                    </td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>

    @if ($loading)
        <p class="sr-only" role="status">読み込み中</p>
    @endif
</div>
