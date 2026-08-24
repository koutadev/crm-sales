@php
    /** @var \Illuminate\Support\Collection $activities */
    $hasActivityErrors = $errors->hasAny(['employee_id', 'type', 'activity_at', 'note']);
@endphp

{{-- 活動履歴(新しい順)とインライン追加 --}}
<div class="space-y-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
     x-data="{ adding: {{ $hasActivityErrors ? 'true' : 'false' }} }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
            活動履歴（{{ $activities->count() }}）
        </h3>

        @can(\App\Enums\PermissionName::MasterManage->value)
            <button type="button" x-on:click="adding = ! adding"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span x-text="adding ? '入力をとじる' : '活動を追加'"></span>
            </button>
        @endcan
    </div>

    @can(\App\Enums\PermissionName::MasterManage->value)
        <div x-show="adding" x-cloak class="rounded-md border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
            <form method="POST" action="{{ route('deals.activities.store', $deal->id) }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-form-field name="type" label="種別" :required="true">
                        <x-select-input id="type" name="type" class="mt-1 block w-full"
                                        :options="$activityTypeOptions"
                                        :selected="old('type', \App\Enums\ActivityType::Phone->value)" required />
                    </x-form-field>

                    <x-form-field name="activity_at" label="実施日時" :required="true">
                        <x-text-input id="activity_at" name="activity_at" type="datetime-local" class="mt-1 block w-full"
                                      :value="old('activity_at', now()->format('Y-m-d\TH:i'))" required />
                    </x-form-field>

                    <x-form-field name="employee_id" label="実施者" :required="true">
                        <x-select-input id="employee_id" name="employee_id" class="mt-1 block w-full"
                                        :options="$employeeOptions"
                                        :selected="old('employee_id', $defaultEmployeeId)"
                                        placeholder="選択してください" required />
                    </x-form-field>
                </div>

                <x-form-field name="note" label="内容">
                    <textarea id="note" name="note" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ old('note') }}</textarea>
                </x-form-field>

                <x-primary-button type="submit">追加</x-primary-button>
            </form>
        </div>
    @endcan

    <div class="divide-y divide-gray-100 dark:divide-gray-700">
        @forelse ($activities as $activity)
            <div class="flex flex-wrap items-start gap-4 py-4 first:pt-0">
                <div class="w-36 shrink-0">
                    <p class="whitespace-nowrap text-sm tabular-nums text-gray-700 dark:text-gray-300">
                        {{ $activity->activity_at->format('Y/m/d H:i') }}
                    </p>
                    <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $activity->type->badgeClass() }}">
                        {{ $activity->type->label() }}
                    </span>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $activity->employee?->name ?? '—' }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-900 dark:text-gray-100">{{ $activity->note ?: '—' }}</p>
                </div>
            </div>
        @empty
            <p class="py-10 text-center text-gray-500 dark:text-gray-400">活動履歴がありません。</p>
        @endforelse
    </div>
</div>
