{{-- 活動タブ: この顧客の活動履歴(新しい順) --}}
<div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
    @forelse ($activities as $activity)
        <div class="flex flex-wrap items-start gap-4 border-b border-gray-100 px-6 py-4 last:border-b-0 dark:border-gray-700">
            <div class="w-36 shrink-0">
                <p class="whitespace-nowrap text-sm tabular-nums text-gray-700 dark:text-gray-300">
                    {{ $activity->activity_at->format('Y/m/d H:i') }}
                </p>
                <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $activity->type->badgeClass() }}">
                    {{ $activity->type->label() }}
                </span>
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $activity->employee?->name ?? '—' }}
                    @if ($activity->deal)
                        ／ <span class="font-mono">{{ $activity->deal->code }}</span> {{ $activity->deal->title }}
                    @endif
                </p>
                <p class="mt-1 whitespace-pre-line text-sm text-gray-900 dark:text-gray-100">{{ $activity->note ?: '—' }}</p>
            </div>
        </div>
    @empty
        <p class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">活動履歴がありません。</p>
    @endforelse
</div>
