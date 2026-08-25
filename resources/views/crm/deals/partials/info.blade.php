{{-- 基本情報 --}}
<x-card title="基本情報">
    <dl class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">顧客</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                @if ($deal->partner)
                    <a href="{{ route('customers.show', $deal->partner_id) }}"
                       class="text-primary-text hover:text-primary-hover hover:underline">
                        {{ $deal->partner->name }}
                    </a>
                @else
                    &mdash;
                @endif
            </dd>
        </div>

        <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">ステータス</dt>
            <dd class="mt-1">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $deal->status->badgeClass() }}">
                    {{ $deal->status->label() }}
                </span>
            </dd>
        </div>

        @foreach ([
            '先方担当' => $deal->partnerContact?->name,
            '営業担当' => $deal->employee?->name,
            '確度' => $deal->probability.'%',
            '予定クローズ日' => $deal->expected_close_date->format('Y/m/d'),
            '受注日' => $deal->ordered_at?->format('Y/m/d'),
            '登録日時' => $deal->created_at?->format('Y/m/d H:i'),
            '最終更新' => $deal->updated_at?->format('Y/m/d H:i'),
        ] as $label => $value)
            <div>
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $value ?: '—' }}</dd>
            </div>
        @endforeach
    </dl>
</x-card>
