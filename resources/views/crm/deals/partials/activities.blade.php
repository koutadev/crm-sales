@php
    /** @var \Illuminate\Support\Collection $activities */
    $canAddActivity = auth()->user()?->can(\App\Enums\PermissionName::MasterManage->value) ?? false;
@endphp

{{-- 活動履歴(新しい順)。追加はモーダルで完結する --}}
<x-card title="活動履歴" :subtitle="'新しい順に最大 100 件。全 '.number_format($activities->count()).' 件'">
    @if ($canAddActivity)
        <x-slot name="actions">
            <x-button type="button" size="sm" x-on:click="$dispatch('open-modal', 'deal-activity')">活動を追加</x-button>
        </x-slot>
    @endif

    <ol class="divide-y divide-gray-100 dark:divide-gray-700">
        @forelse ($activities as $activity)
            <li @class([
                'flex flex-wrap items-start gap-4 py-4 first:pt-0',
                // これからの予定は色を変えて、済んだ活動と見分けられるようにする
                'opacity-90' => $activity->activity_at->isFuture(),
            ])>
                <div class="w-40 shrink-0">
                    <p class="whitespace-nowrap text-sm tabular-nums text-gray-700 dark:text-gray-300">
                        {{ $activity->activity_at->format('Y/m/d H:i') }}
                    </p>
                    <p class="mt-0.5 flex items-center gap-1.5">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $activity->type->badgeClass() }}">
                            {{ $activity->type->label() }}
                        </span>
                        @if ($activity->activity_at->isFuture())
                            <x-badge tone="info">予定</x-badge>
                        @endif
                    </p>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $activity->employee?->name ?? '—' }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-900 dark:text-gray-100">{{ $activity->note ?: '—' }}</p>
                </div>
            </li>
        @empty
            <li class="py-10 text-center text-gray-500 dark:text-gray-400">活動履歴がありません。</li>
        @endforelse
    </ol>
</x-card>

@if ($canAddActivity)
    {{-- 活動の追加(エラー時はこのモーダルが開いた状態で戻る) --}}
    <x-modal name="deal-activity" title="活動を追加" size="md">
        <form method="POST" action="{{ route('deals.activities.store', $deal->id) }}" class="space-y-4">
            @csrf
            <x-modal-marker name="deal-activity" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.select name="type" label="種別" :required="true"
                               :options="$activityTypeOptions"
                               :selected="old('type', \App\Enums\ActivityType::Phone->value)" />

                <x-form.field name="activity_at" label="実施日時" :required="true"
                              help="先の日時を入れると「次アクション」として上部に出ます。">
                    <input type="datetime-local" id="activity_at" name="activity_at"
                           value="{{ old('activity_at', now()->format('Y-m-d\TH:i')) }}" required
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                </x-form.field>
            </div>

            <x-form.combobox name="employee_id" label="実施者" :required="true"
                             :options="$employeeOptions"
                             :selected="old('employee_id', $defaultEmployeeId)"
                             placeholder="担当者名・コードで検索" />

            <x-form.textarea name="note" label="内容" rows="4" :value="old('note')"
                             placeholder="訪問・電話の内容や次の約束など" />

            <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">キャンセル</x-button>
                <x-button type="submit">追加</x-button>
            </div>
        </form>
    </x-modal>
@endif
