@props([
    'table',
    'actions' => true,
])

@php
    /** @var \App\Support\DataTable\Table $table */
    $state = $table->state;
@endphp

<div class="space-y-4">
    {{-- 絞り込み(保存ビュー + 検索フォーム) --}}
    <x-table-filters :table="$table">
        @isset($extraFilters)
            <x-slot name="extraFilters">{{ $extraFilters }}</x-slot>
        @endisset
    </x-table-filters>

    {{-- 件数・CSV・削除済み表示 --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            全 {{ number_format($table->paginator->total()) }} 件
            @if ($table->paginator->total() > 0)
                （{{ number_format($table->paginator->firstItem() ?? 0) }}–{{ number_format($table->paginator->lastItem() ?? 0) }} 件目を表示）
            @endif
        </p>

        <div class="flex flex-wrap items-center gap-3">
            @if ($table->canViewTrashed)
                {{-- 削除済みの表示切り替えは管理者のみ --}}
                <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs dark:border-gray-600">
                    @foreach (['' => '通常', 'with' => '削除済みも表示', 'only' => '削除済みのみ'] as $mode => $label)
                        <a href="{{ $table->trashedUrl($mode) }}"
                           @class([
                               'px-3 py-1.5',
                               'bg-primary text-white' => $state->trashed === $mode,
                               'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $state->trashed !== $mode,
                           ])>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($table->definition->exportable())
                <a href="{{ $table->exportUrl() }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    CSV出力
                </a>
            @endif
        </div>
    </div>

    {{-- 一覧(共通のテーブル部品に載せる) --}}
    <x-table :columns="$table->columns()"
             :sort="$state->sort"
             :direction="$state->direction"
             :sort-url="fn (\App\Support\DataTable\Column $column): string => $table->sortUrl($column)"
             :actions="$actions"
             :is-empty="$table->isEmpty()"
             :empty="$state->hasConditions() ? '条件に一致するデータがありません。' : 'データが登録されていません。'">
        {{ $slot }}
    </x-table>

    <x-pagination :paginator="$table->paginator" />
</div>
