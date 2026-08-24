{{-- 商談タブ: この顧客の商談一覧(一覧・詳細画面は STEP 5) --}}
<div class="space-y-4">
    @can(\App\Enums\PermissionName::MasterManage->value)
        <div class="flex justify-end">
            <a href="{{ route('deals.create', ['customer' => $customer->id]) }}"
               class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-primary-hover">
                商談を追加
            </a>
        </div>
    @endcan

<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900/40">
            <tr>
                @foreach (['商談コード' => 'left', '件名' => 'left', 'ステータス' => 'center', '確度' => 'right', '金額(税込)' => 'right', '予定クローズ日' => 'left', '受注日' => 'left', '営業担当' => 'left'] as $label => $align)
                    <th scope="col" class="px-4 py-3 text-{{ $align }} text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $label }}
                    </th>
                @endforeach
                <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">操作</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse ($deals as $deal)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $deal->code }}</td>
                    <td class="px-4 py-3 font-medium">{{ $deal->title }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $deal->status->badgeClass() }}">
                            {{ $deal->status->label() }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                        {{ $deal->probability }}%
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                        {{ number_format($deal->amount_total) }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                        {{ $deal->expected_close_date->format('Y/m/d') }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                        {{ $deal->ordered_at?->format('Y/m/d') ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                        {{ $deal->employee?->name ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @can(\App\Enums\PermissionName::MasterManage->value)
                            <a href="{{ route('deals.edit', $deal->id) }}"
                               class="text-xs font-medium text-primary-text hover:text-primary-hover">
                                編集
                            </a>
                        @else
                            <span class="text-xs text-gray-400">&mdash;</span>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                        商談が登録されていません。
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
</div>
