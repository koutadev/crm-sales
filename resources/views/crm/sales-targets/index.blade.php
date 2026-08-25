@php
    /** @var \App\Support\Crm\TargetLabels $labels */
@endphp

<x-master-index :table="$table" :resource-label="$resourceLabel" :route-name="$routeName" :initial-detail="$initialDetail ?? null">
    <x-slot name="headerActions">
        @can(\App\Enums\PermissionName::MasterManage->value)
            <x-button type="button" variant="secondary" size="sm"
                      x-on:click="$dispatch('open-modal', 'duplicate-targets')">前の期間から複製</x-button>
        @endcan
    </x-slot>

    @foreach ($table->items() as $target)
        <x-table.row :muted="$target->trashed()"
                     :detail-url="route($routeName.'.detail', $target->id)">
            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $target->code }}</td>

            <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $target->periodLabel() }}</td>

            <td class="whitespace-nowrap px-4 py-3 text-center">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $target->scope->badgeClass() }}">
                    {{ $target->scope->label() }}
                </span>
            </td>

            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $labels->of($target) }}</td>

            <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">
                {{ number_format($target->amount) }}
            </td>

            <td class="whitespace-nowrap px-4 py-3 text-center">
                <x-active-badge :active="$target->is_active" :trashed="$target->trashed()" />
            </td>

            <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                {{ $target->updated_at?->format('Y/m/d H:i') }}
            </td>

            <x-master-row-actions :record="$target" :route-name="$routeName" :resource-label="$resourceLabel" />
        </x-table.row>
    @endforeach
</x-master-index>

@can(\App\Enums\PermissionName::MasterManage->value)
    {{-- 前の期間からまとめて複製する(毎月ゼロから入れ直さないため) --}}
    <x-modal name="duplicate-targets" title="前の期間から複製" size="md">
        <form method="POST" action="{{ route('masters.sales-targets.duplicate') }}" class="space-y-4">
            @csrf

            <p class="text-sm text-gray-600 dark:text-gray-400">
                指定した年月の目標を、まとめて別の年月に複製します。
                複製先にすでに同じ対象の目標がある場合は、そのままにします（上書きしません）。
            </p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-md border border-gray-200 p-4 dark:border-gray-700">
                    <p class="mb-3 text-xs font-medium text-gray-500 dark:text-gray-400">複製もと</p>
                    <div class="grid grid-cols-2 gap-3">
                        <x-form.select name="from_year" label="年" :options="$yearOptions ?? []"
                                       :selected="old('from_year', $copyFrom->year)" required />
                        <x-form.select name="from_month" label="月" :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => $m.'月'])->all()"
                                       :selected="old('from_month', $copyFrom->month)" required />
                    </div>
                </div>

                <div class="rounded-md border border-gray-200 p-4 dark:border-gray-700">
                    <p class="mb-3 text-xs font-medium text-gray-500 dark:text-gray-400">複製さき</p>
                    <div class="grid grid-cols-2 gap-3">
                        <x-form.select name="to_year" label="年" :options="$yearOptions ?? []"
                                       :selected="old('to_year', $copyTo->year)" required />
                        <x-form.select name="to_month" label="月" :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => $m.'月'])->all()"
                                       :selected="old('to_month', $copyTo->month)" required />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">キャンセル</x-button>
                <x-button type="submit">複製する</x-button>
            </div>
        </form>
    </x-modal>
@endcan
