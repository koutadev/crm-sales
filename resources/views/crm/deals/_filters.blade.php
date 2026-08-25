{{-- 商談一覧・カンバンで共通の絞り込み(基準日・期間・確度) --}}
{{-- 表とカンバンのどちらから検索しても、いまの見せ方のまま戻る --}}
<input type="hidden" name="view_mode" value="{{ $viewMode }}">

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
