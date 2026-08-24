@php
    use App\Enums\PermissionName;

    $canManage = auth()->user()?->can(PermissionName::MasterManage->value) ?? false;
    // 入力エラーで戻ってきたときは、そのフォームを開いた状態にする
    $openFormId = old('contact_id') !== null ? (int) old('contact_id') : ($errors->any() ? 0 : null);
@endphp

{{-- 担当者タブ: 会社に紐づく担当者をこの中だけで追加・編集・無効化する --}}
<div class="space-y-4" x-data="{ editing: {{ $openFormId === null ? 'null' : $openFormId }} }">
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/40">
                <tr>
                    @foreach (['氏名', '部署', '役職', 'メールアドレス', '電話番号', '状態'] as $label)
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $label }}
                        </th>
                    @endforeach
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        操作
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($contacts as $contact)
                    <tr class="{{ $contact->is_active ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3 font-medium">{{ $contact->name }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $contact->department ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $contact->position ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $contact->email ?: '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">{{ $contact->phone ?: '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <x-active-badge :active="$contact->is_active" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($canManage)
                                <button type="button" x-on:click="editing = (editing === {{ $contact->id }} ? null : {{ $contact->id }})"
                                        class="text-xs font-medium text-primary-text hover:text-primary-hover">
                                    編集
                                </button>
                            @else
                                <span class="text-xs text-gray-400">&mdash;</span>
                            @endif
                        </td>
                    </tr>

                    @if ($canManage)
                        <tr x-show="editing === {{ $contact->id }}" x-cloak>
                            <td colspan="7" class="bg-gray-50 px-4 py-5 dark:bg-gray-900/40">
                                @include('crm.customers.partials.contact-form', ['contact' => $contact])
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                            担当者が登録されていません。
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canManage)
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">担当者を追加</h3>

            <div class="mt-4">
                @include('crm.customers.partials.contact-form', ['contact' => null])
            </div>
        </div>
    @endif
</div>
