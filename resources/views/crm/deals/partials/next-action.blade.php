{{-- 次アクション(これから予定されている活動のうち、いちばん近いもの) --}}
<x-card title="次アクション">
    @if ($nextAction)
        <div class="flex flex-wrap items-start gap-4">
            <div class="w-40 shrink-0">
                <p class="text-sm font-medium tabular-nums text-gray-900 dark:text-gray-100">
                    {{ $nextAction->activity_at->format('Y/m/d H:i') }}
                </p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ $nextAction->activity_at->diffForHumans() }}
                </p>
            </div>

            <div class="min-w-0 flex-1">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $nextAction->type->badgeClass() }}">
                    {{ $nextAction->type->label() }}
                </span>
                <span class="ms-2 text-xs text-gray-500 dark:text-gray-400">{{ $nextAction->employee?->name ?? '—' }}</span>

                <p class="mt-1 whitespace-pre-line text-sm text-gray-900 dark:text-gray-100">{{ $nextAction->note ?: '—' }}</p>
            </div>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                予定されている活動はありません。
                @can(\App\Enums\PermissionName::MasterManage->value)
                    先の日時で活動を登録すると、ここに次の予定として出ます。
                @endcan
            </p>

            @can(\App\Enums\PermissionName::MasterManage->value)
                <x-button type="button" variant="secondary" size="sm"
                          x-on:click="$dispatch('open-modal', 'deal-activity')">活動を追加</x-button>
            @endcan
        </div>
    @endif
</x-card>
