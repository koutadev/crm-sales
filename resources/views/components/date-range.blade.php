@props([
    'name',
    'label' => null,
    'basisLabel' => null,
    'basis' => null,
    'presets' => null,
    'preset' => null,
    'from' => null,
    'to' => null,
    'submitOnChange' => false,
    'disabled' => false,
    'help' => null,
    'id' => null,
])

@php
    use App\Support\Ui\DateRange;
    use App\Support\Ui\DateRangePreset;

    $inputId = $id ?? $name;

    // 明示されなければ、いまのリクエスト(絞り込み後の再表示)から状態を復元する
    $current = $preset !== null || $from !== null || $to !== null
        ? new DateRange(
            DateRangePreset::resolve($preset, $from !== null || $to !== null ? DateRangePreset::Custom : DateRangePreset::None),
            $from !== null ? \Carbon\CarbonImmutable::parse($from) : null,
            $to !== null ? \Carbon\CarbonImmutable::parse($to) : null,
        )
        : DateRange::fromRequest(request(), $name);

    // 相対プリセットは「いま」の期間を添えて渡す(表示と入力欄の初期値用)。
    // 送信されるのはキーなので、絞り込み時はサーバ側で計算し直される。
    $presetItems = [];

    foreach ($presets ?? DateRangePreset::relative() as $item) {
        $item = DateRangePreset::resolve($item);
        [$presetFrom, $presetTo] = $item->range() ?? [null, null];

        $presetItems[] = [
            'value' => $item->value,
            'label' => $item->label(),
            'from' => $presetFrom?->toDateString() ?? '',
            'to' => $presetTo?->toDateString() ?? '',
        ];
    }

    $config = [
        'presets' => $presetItems,
        'preset' => $current->preset->value,
        'from' => $current->from?->toDateString() ?? '',
        'to' => $current->to?->toDateString() ?? '',
        'submitOnChange' => (bool) $submitOnChange,
        'noneLabel' => DateRangePreset::None->label(),
    ];
@endphp

{{--
    日付範囲ピッカー。

        <x-date-range name="closed" label="期間" basis-label="予定クローズ日" />

    送信されるのは 3 つの hidden。
        closed_preset … 相対プリセットのキー(this_month など) / custom / none
        closed_from, closed_to … カスタム指定の開始日・終了日

    受け取る側は DateRange::fromRequest($request, 'closed') で期間に解決する。
    相対プリセットは毎回そこで計算されるので、月が替わっても指定し直す必要がない。

    「どの日付で絞るか(基準日)」は呼び出し側から渡す。
    basis を渡すと {name}_basis として一緒に送られるので、
    基準日の切り替え UI(ラジオなど)を隣に置いて連携できる。
--}}
<x-form.field :name="$name" :label="$label" :for="$inputId" :help="$help">
    <div x-data="dateRange(@js($config))"
         @keydown.escape.stop="open = false"
         @click.outside="open = false"
         class="relative">

        <input type="hidden" name="{{ $name }}_preset" :value="preset">
        <input type="hidden" name="{{ $name }}_from" :value="from">
        <input type="hidden" name="{{ $name }}_to" :value="to">

        @if ($basis !== null)
            <input type="hidden" name="{{ $name }}_basis" value="{{ $basis }}">
        @endif

        <div class="flex items-center gap-1">
            <button type="button"
                    id="{{ $inputId }}"
                    @click="open = ! open"
                    @disabled($disabled)
                    aria-haspopup="dialog"
                    :aria-expanded="open.toString()"
                    aria-controls="{{ $inputId }}-panel"
                    {{ $attributes->merge(['class' => 'inline-flex w-full items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 motion-reduce:transition-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800']) }}>
                <x-icon name="calendar" class="h-4 w-4 shrink-0 text-gray-400" />

                @if ($basisLabel !== null)
                    <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $basisLabel }}：</span>
                @endif

                <span class="truncate" x-text="label"></span>
            </button>

            <button type="button"
                    x-show="hasRange"
                    x-cloak
                    @click="clear()"
                    @disabled($disabled)
                    class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 motion-reduce:transition-none dark:hover:bg-gray-700"
                    aria-label="期間の指定を解除">
                <x-icon name="close" class="h-4 w-4" />
            </button>
        </div>

        {{-- ポップオーバー --}}
        <div id="{{ $inputId }}-panel"
             x-show="open"
             x-cloak
             x-transition.opacity.duration.100ms
             role="dialog"
             aria-label="期間を選ぶ"
             class="absolute z-30 mt-1 w-max max-w-[calc(100vw-2rem)] rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-800">

            <div class="flex flex-col gap-4 sm:flex-row">
                {{-- 相対プリセット --}}
                <div class="sm:w-44">
                    <p class="px-1 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        よく使う期間
                    </p>

                    <ul class="space-y-0.5">
                        <template x-for="item in presets" :key="item.value">
                            <li>
                                <button type="button"
                                        @click="selectPreset(item)"
                                        :aria-pressed="isSelected(item.value).toString()"
                                        :class="isSelected(item.value)
                                            ? 'bg-primary-soft font-semibold text-primary-soft-fg'
                                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700'"
                                        class="flex w-full items-center justify-between gap-3 rounded-md px-2 py-1.5 text-start text-sm transition-colors motion-reduce:transition-none">
                                    <span x-text="item.label"></span>
                                    <span class="text-[10px] text-gray-400" x-text="format(item.from)"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- カスタム期間 --}}
                <div class="sm:w-56 sm:border-s sm:border-gray-100 sm:ps-4 dark:sm:border-gray-700">
                    <p class="pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        期間を指定
                    </p>

                    <div class="space-y-2">
                        <div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">開始日</span>
                            <x-datepicker x-model="from" size="sm" class="mt-0.5"
                                          @datepicker-changed="onCustomInput()" />
                        </div>

                        <div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">終了日</span>
                            <x-datepicker x-model="to" size="sm" class="mt-0.5"
                                          @datepicker-changed="onCustomInput()" />
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <x-button type="button" variant="ghost" size="sm" x-on:click="clear()">指定なし</x-button>
                        <x-button type="button" size="sm" x-on:click="apply()">適用</x-button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-form.field>
