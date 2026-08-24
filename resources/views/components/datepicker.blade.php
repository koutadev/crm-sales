@props([
    'name' => null,
    'value' => null,
    'id' => null,
    'size' => 'md',
    'required' => false,
    'disabled' => false,
    'min' => null,
    'max' => null,
    'placeholder' => 'YYYY/MM/DD',
    'hasError' => false,
])

@php
    use App\Support\Ui\Contracts\HolidayProvider;
    use App\Support\Ui\Input;
    use App\Support\Ui\Size;
    use Carbon\CarbonImmutable;

    $inputId = $id ?? $name ?? 'datepicker-'.uniqid();

    $selected = $value !== null && $value !== '' ? CarbonImmutable::parse($value)->toDateString() : '';
    $base = $selected !== '' ? CarbonImmutable::parse($selected) : CarbonImmutable::now();

    // 「特別な日(祝日など)」は差し替え可能な HolidayProvider から受け取る。
    // 既定の実装は何も返さないので、いまは空のまま。
    $holidays = app(HolidayProvider::class)->between($base->subMonths(6), $base->addMonths(6));

    $config = [
        'value' => $selected,
        'holidays' => $holidays,
        'min' => $min ?? '',
        'max' => $max ?? '',
        'weekStart' => (int) config('ui.week_starts_on', 1),
    ];
@endphp

{{--
    カレンダー(日付選択)。

        <x-datepicker name="expected_close_date" :value="$deal->expected_close_date" />

    x-model にも対応しているので、他の Alpine 部品から値を共有できる。

        <x-datepicker x-model="from" />

    祝日のハイライトは App\Support\Ui\Contracts\HolidayProvider を差し替えると効く
    (既定は祝日なし)。
--}}
<div x-data="datepicker(@js($config))"
     x-modelable="value"
     @keydown.escape.stop="close()"
     @click.outside="close()"
     {{ $attributes->merge(['class' => 'relative']) }}>

    @if ($name !== null)
        <input type="hidden" name="{{ $name }}" :value="value">
    @endif

    <div class="relative">
        <input type="text"
               id="{{ $inputId }}"
               x-model="display"
               @blur="parseDisplay()"
               @keydown.enter.prevent="parseDisplay(); close()"
               @keydown.arrow-down.prevent="open = true; $nextTick(() => focusDay())"
               placeholder="{{ $placeholder }}"
               autocomplete="off"
               inputmode="numeric"
               @required($required)
               @disabled($disabled)
               @if ($hasError) aria-invalid="true" @endif
               class="{{ Input::classes(Size::resolve($size), $hasError) }} pe-10">

        <button type="button"
                @click="toggle()"
                @disabled($disabled)
                class="absolute inset-y-0 end-0 flex items-center px-2 text-gray-400 transition hover:text-gray-600 motion-reduce:transition-none dark:hover:text-gray-200"
                aria-haspopup="dialog"
                :aria-expanded="open.toString()"
                aria-label="カレンダーを開く">
            <x-icon name="calendar" class="h-4 w-4" />
        </button>
    </div>

    {{-- カレンダー --}}
    <div x-show="open"
         x-cloak
         x-transition.opacity.duration.100ms
         role="dialog"
         aria-label="日付を選ぶ"
         class="absolute z-40 mt-1 w-72 rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-800">

        {{-- 年月の切り替え --}}
        <div class="flex items-center justify-between gap-2">
            <button type="button" @click="shiftMonth(-1)"
                    class="rounded p-1 text-gray-500 transition hover:bg-gray-100 motion-reduce:transition-none dark:hover:bg-gray-700"
                    aria-label="前の月">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </button>

            <div class="flex items-center gap-1">
                <select x-model.number="viewYear" aria-label="年"
                        class="rounded-md border-gray-300 py-1 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                    <template x-for="year in years" :key="year">
                        <option :value="year" x-text="`${year}年`"></option>
                    </template>
                </select>

                <select x-model.number="viewMonth" aria-label="月"
                        class="rounded-md border-gray-300 py-1 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                    <template x-for="month in months" :key="month">
                        <option :value="month" x-text="`${month}月`"></option>
                    </template>
                </select>
            </div>

            <button type="button" @click="shiftMonth(1)"
                    class="rounded p-1 text-gray-500 transition hover:bg-gray-100 motion-reduce:transition-none dark:hover:bg-gray-700"
                    aria-label="次の月">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </button>
        </div>

        <p class="sr-only" aria-live="polite" x-text="monthLabel"></p>

        {{-- 曜日 --}}
        <div class="mt-2 grid grid-cols-7 gap-0.5 text-center text-xs">
            <template x-for="weekday in weekdays" :key="weekday.dow">
                <span :class="{
                          'text-rose-500': weekday.dow === 0,
                          'text-sky-500': weekday.dow === 6,
                          'text-gray-500 dark:text-gray-400': weekday.dow !== 0 && weekday.dow !== 6,
                      }"
                      class="py-1" x-text="weekday.label"></span>
            </template>
        </div>

        {{-- 日付 --}}
        <div x-ref="grid"
             role="grid"
             @keydown.arrow-left.prevent="moveFocus(-1)"
             @keydown.arrow-right.prevent="moveFocus(1)"
             @keydown.arrow-up.prevent="moveFocus(-7)"
             @keydown.arrow-down.prevent="moveFocus(7)"
             @keydown.page-up.prevent="shiftMonth(-1)"
             @keydown.page-down.prevent="shiftMonth(1)"
             class="mt-1 space-y-0.5">
            <template x-for="(week, index) in weeks" :key="index">
                <div class="grid grid-cols-7 gap-0.5" role="row">
                    <template x-for="day in week" :key="day.key">
                        <button type="button"
                                role="gridcell"
                                :data-date="day.key"
                                :tabindex="focused === day.key ? 0 : -1"
                                :aria-selected="isSelected(day.key).toString()"
                                :aria-label="day.label + (day.holiday ? '（' + day.holiday + '）' : '')"
                                :disabled="day.disabled"
                                @click="pick(day.key)"
                                @focus="focused = day.key"
                                :class="{
                                    'bg-primary text-white font-semibold hover:bg-primary-hover': isSelected(day.key),
                                    'ring-1 ring-primary': ! isSelected(day.key) && isToday(day.key),
                                    'opacity-40': ! day.inMonth,
                                    'text-rose-500': ! isSelected(day.key) && dayTone(day) === 'sunday',
                                    'text-sky-600': ! isSelected(day.key) && dayTone(day) === 'saturday',
                                    'text-gray-700 dark:text-gray-200': ! isSelected(day.key) && dayTone(day) === 'weekday',
                                    'hover:bg-gray-100 dark:hover:bg-gray-700': ! isSelected(day.key) && ! day.disabled,
                                    'cursor-not-allowed opacity-30': day.disabled,
                                }"
                                class="relative rounded py-1.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary motion-reduce:transition-none">
                            <span x-text="day.day"></span>
                            {{-- 祝日などの目印(HolidayProvider を差し替えると出る) --}}
                            <span x-show="day.holiday" class="absolute inset-x-0 bottom-0.5 mx-auto h-1 w-1 rounded-full bg-rose-500"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        <div class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2 dark:border-gray-700">
            <x-button type="button" variant="ghost" size="sm" x-on:click="clear()">クリア</x-button>
            <x-button type="button" variant="secondary" size="sm" x-on:click="selectToday()">今日</x-button>
        </div>
    </div>
</div>
