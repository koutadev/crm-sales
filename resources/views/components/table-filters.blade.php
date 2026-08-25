@props(['table'])

@php
    /** @var \App\Support\DataTable\Table $table */
    $state = $table->state;
@endphp

{{--
    一覧の絞り込み欄(保存ビュー + 検索フォーム)。

    x-data-table が内側で使うほか、表以外の見せ方(カンバンなど)でも
    同じ絞り込みを出したいときに単独で使える。

        <x-table-filters :table="$table">
            <x-slot name="extraFilters"> … </x-slot>
        </x-table-filters>
--}}
<div class="space-y-4">
    @if ($table->definition->savedViews())
        {{-- 保存ビュー(マイビュー) --}}
        <x-saved-views :table="$table" />
    @endif

    {{-- 検索・絞り込み --}}
    <form method="GET" action="{{ $table->indexUrl() }}"
          class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        {{-- 並び順と削除済み表示は検索しても維持する --}}
        <input type="hidden" name="sort" value="{{ $state->sort }}">
        <input type="hidden" name="direction" value="{{ $state->direction }}">
        @if ($state->trashed !== '')
            <input type="hidden" name="trashed" value="{{ $state->trashed }}">
        @endif

        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <label for="dt-q" class="block text-xs font-medium text-gray-600 dark:text-gray-400">キーワード</label>
                <input id="dt-q" type="search" name="q" value="{{ $state->search }}"
                       placeholder="{{ $table->definition->searchPlaceholder() }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
            </div>

            @foreach ($table->filters() as $filter)
                <div @class(['w-56' => $filter->isCombobox()])>
                    <label for="dt-{{ $filter->name }}" class="block text-xs font-medium text-gray-600 dark:text-gray-400">
                        {{ $filter->label }}
                    </label>

                    @if ($filter->isCombobox())
                        {{-- 候補が多いマスタ(顧客・担当者など)は入力で絞り込む --}}
                        <div class="mt-1">
                            <x-form.combobox :name="$filter->name" :id="'dt-'.$filter->name"
                                             :options="$filter->options"
                                             :source="$filter->source"
                                             :selected="$state->filterValue($filter->name)"
                                             :selected-label="$filter->labelFor($state->filterValue($filter->name))"
                                             :placeholder="$filter->placeholder"
                                             :empty="'該当する'.$filter->label.'がありません'"
                                             size="sm" />
                        </div>
                    @else
                        <select id="dt-{{ $filter->name }}" name="{{ $filter->name }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 sm:text-sm">
                            <option value="">{{ $filter->placeholder }}</option>
                            @foreach ($filter->options as $value => $label)
                                <option value="{{ $value }}" @selected($state->filterValue($filter->name) === (string) $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endforeach

            @isset($extraFilters)
                {{-- 期間フィルタなど、セレクト以外の絞り込み --}}
                {{ $extraFilters }}
            @endisset

            <div class="flex items-center gap-2">
                <x-primary-button type="submit">検索</x-primary-button>

                @if ($state->hasConditions())
                    <a href="{{ $table->resetUrl() }}"
                       class="text-sm text-gray-500 underline hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        条件をクリア
                    </a>
                @endif
            </div>
        </div>
    </form>

</div>
